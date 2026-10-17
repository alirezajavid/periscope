import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin API for the question inbox, comments, listening statistics and leak checks. */
export const useAdminCommunityStore = defineStore('adminCommunity', () => {
  const questions = (params) => api.get('/api/admin/questions', { params }).then((r) => r.data)
  const star = (id) => api.post(`/api/admin/questions/${id}/star`).then((r) => r.data.data)
  const answer = (id, payload) =>
    api.post(`/api/admin/questions/${id}/answer`, payload).then((r) => r.data.data)

  const comments = (params) => api.get('/api/admin/comments', { params }).then((r) => r.data)
  const hideComment = (id) => api.post(`/api/admin/comments/${id}/hide`).then((r) => r.data.data)
  const unhideComment = (id) =>
    api.post(`/api/admin/comments/${id}/unhide`).then((r) => r.data.data)
  const replyToComment = (id, body) =>
    api.post(`/api/admin/comments/${id}/reply`, { body }).then((r) => r.data.data)

  const listeningStats = (page = 1) =>
    api.get('/api/admin/stats/listening', { params: { page } }).then((r) => r.data)

  const checkText = (text) => api.post('/api/admin/leak-checks/text', { text }).then((r) => r.data)
  const checkAudio = (file) => {
    const body = new FormData()
    body.append('audio', file)
    return api.post('/api/admin/leak-checks/audio', body, { timeout: 0 }).then((r) => r.data)
  }
  const leakHistory = (page = 1) =>
    api.get('/api/admin/leak-checks', { params: { page } }).then((r) => r.data)

  return {
    questions,
    star,
    answer,
    comments,
    hideComment,
    unhideComment,
    replyToComment,
    listeningStats,
    checkText,
    checkAudio,
    leakHistory,
  }
})
