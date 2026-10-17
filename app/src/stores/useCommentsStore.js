import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Comments of the post currently open (threads newest first, replies oldest first) and reactions. */
export const useCommentsStore = defineStore('comments', () => {
  const postId = ref(null)
  const items = ref([])
  const page = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')

  const hasMore = () => page.value < lastPage.value

  async function load(id) {
    postId.value = id
    items.value = []
    page.value = 0
    lastPage.value = 1
    await loadMore()
  }

  async function loadMore() {
    if (loading.value || !hasMore() || !postId.value) return
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get(`/api/posts/${postId.value}/comments`, {
        params: { page: page.value + 1 },
      })
      items.value.push(...data.data)
      page.value = data.meta.current_page
      lastPage.value = data.meta.last_page
    } catch (e) {
      error.value = e.response?.data?.message || ''
    } finally {
      loading.value = false
    }
  }

  /** New comment, or a reply when `parentId` is given (the API attaches it to the thread root). */
  async function add(body, parentId = null) {
    const { data } = await api.post(`/api/posts/${postId.value}/comments`, {
      body,
      parent_id: parentId,
    })
    const comment = {
      ...data.data,
      replies: [],
      reactions: data.data.reactions || { likes: 0, dislikes: 0, mine: null },
    }
    if (comment.parent_id) {
      items.value.find((c) => c.id === comment.parent_id)?.replies.push(comment)
    } else {
      items.value.unshift(comment)
    }
  }

  async function remove(comment) {
    await api.delete(`/api/comments/${comment.id}`)
    if (comment.parent_id) {
      const root = items.value.find((c) => c.id === comment.parent_id)
      if (root) root.replies = root.replies.filter((r) => r.id !== comment.id)
      return
    }
    const index = items.value.findIndex((c) => c.id === comment.id)
    if (index === -1) return
    // A thread with replies keeps an empty placeholder, as the API does.
    if (items.value[index].replies.length) {
      items.value[index] = { ...items.value[index], deleted: true, body: null, author: null, mine: false }
    } else {
      items.value.splice(index, 1)
    }
  }

  /** `type` is 'like', 'dislike' or null (remove). Returns the new summary. */
  async function reactToComment(comment, type) {
    const { data } = await api.put(`/api/comments/${comment.id}/reaction`, { type })
    comment.reactions = data.data
    return data.data
  }

  async function reactToPost(id, type) {
    const { data } = await api.put(`/api/posts/${id}/reaction`, { type })
    return data.data
  }

  return {
    items,
    loading,
    error,
    hasMore,
    load,
    loadMore,
    add,
    remove,
    reactToComment,
    reactToPost,
  }
})
