// The only axios instance factory for app/ and admin/.
// axios itself is passed in so this folder needs no node_modules of its own.

const CSRF_PATH = "/sanctum/csrf-cookie";
const WRITE_METHODS = ["post", "put", "patch", "delete"];

/**
 * @param {import('axios').AxiosStatic} axios
 * @param {{ baseURL: string, onUnauthenticated?: (status: number) => void, csrfTokenPath?: string }} options
 *   onUnauthenticated is called on 401/419 (session gone or CSRF token expired).
 *   csrfTokenPath: by default the Sanctum cookie flow is used (`/sanctum/csrf-cookie` + XSRF-TOKEN cookie).
 *   The admin app shares its cookie domain with the subscriber app, so it passes a path that returns
 *   `{ token }` instead; the token is then sent in the X-CSRF-TOKEN header.
 */
export function createClient(axios, { baseURL, onUnauthenticated, csrfTokenPath }) {
  const client = axios.create({
    baseURL,
    withCredentials: true,
    withXSRFToken: !csrfTokenPath,
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });

  let csrfReady = false;

  client.interceptors.request.use(async (config) => {
    if (!csrfReady && WRITE_METHODS.includes(config.method)) {
      if (csrfTokenPath) {
        const { data } = await axios.get(csrfTokenPath, { baseURL, withCredentials: true });
        config.headers.set("X-CSRF-TOKEN", data.token);
        client.defaults.headers.common["X-CSRF-TOKEN"] = data.token;
      } else {
        await axios.get(CSRF_PATH, { baseURL, withCredentials: true });
      }
      csrfReady = true;
    }
    return config;
  });

  client.interceptors.response.use(
    (response) => response,
    (error) => {
      const status = error.response?.status;
      if (status === 419) csrfReady = false;
      // Logging in regenerates the session, which invalidates the CSRF token fetched before it:
      // fetch a new one and repeat the request once.
      if (status === 419 && csrfTokenPath && !error.config.csrfRetried) {
        error.config.csrfRetried = true;
        return client.request(error.config);
      }
      if ((status === 401 || status === 419) && onUnauthenticated)
        onUnauthenticated(status);
      return Promise.reject(error);
    },
  );

  return client;
}
