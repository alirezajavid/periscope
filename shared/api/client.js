// The only axios instance factory for app/ and admin/.
// axios itself is passed in so this folder needs no node_modules of its own.

const CSRF_PATH = "/sanctum/csrf-cookie";
const WRITE_METHODS = ["post", "put", "patch", "delete"];

/**
 * @param {import('axios').AxiosStatic} axios
 * @param {{ baseURL: string, onUnauthenticated?: (status: number) => void }} options
 *   onUnauthenticated is called on 401/419 (session gone or CSRF token expired).
 */
export function createClient(axios, { baseURL, onUnauthenticated }) {
  const client = axios.create({
    baseURL,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });

  let csrfReady = false;

  client.interceptors.request.use(async (config) => {
    if (!csrfReady && WRITE_METHODS.includes(config.method)) {
      await axios.get(CSRF_PATH, { baseURL, withCredentials: true });
      csrfReady = true;
    }
    return config;
  });

  client.interceptors.response.use(
    (response) => response,
    (error) => {
      const status = error.response?.status;
      if (status === 419) csrfReady = false;
      if ((status === 401 || status === 419) && onUnauthenticated)
        onUnauthenticated(status);
      return Promise.reject(error);
    },
  );

  return client;
}
