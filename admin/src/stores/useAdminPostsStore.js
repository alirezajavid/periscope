import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin API for posts: list, edit, uploads, watermark status. All calls go through the shared client. */
export const useAdminPostsStore = defineStore('adminPosts', () => {
  async function list(params) {
    const { data } = await api.get('/api/admin/posts', { params })
    return data
  }

  async function get(id) {
    const { data } = await api.get(`/api/admin/posts/${id}`)
    return data.data
  }

  async function save(id, payload) {
    const { data } = id
      ? await api.put(`/api/admin/posts/${id}`, payload)
      : await api.post('/api/admin/posts', payload)
    return data.data
  }

  const remove = (id) => api.delete(`/api/admin/posts/${id}`)
  const restore = (id) => api.post(`/api/admin/posts/${id}/restore`)

  async function upload(id, kind, file, onProgress) {
    const body = new FormData()
    body.append(kind, file)
    const { data } = await api.post(`/api/admin/posts/${id}/${kind}`, body, {
      onUploadProgress: (event) => onProgress?.(event.total ? event.loaded / event.total : 0),
      timeout: 0,
    })
    return data.data
  }

  async function watermarks(id, status) {
    const { data } = await api.get(`/api/admin/posts/${id}/watermarks`, { params: { status } })
    return data.data
  }

  const retryWatermarks = (id) =>
    api.post(`/api/admin/posts/${id}/watermarks/retry`).then((r) => r.data.queued)

  async function tags() {
    const { data } = await api.get('/api/admin/tags')
    return data.data
  }

  return { list, get, save, remove, restore, upload, watermarks, retryWatermarks, tags }
})
