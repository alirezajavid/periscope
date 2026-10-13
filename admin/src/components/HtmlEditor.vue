<template>
  <q-editor
    v-model="model"
    min-height="12rem"
    paragraph-tag="p"
    dir="rtl"
    :toolbar="toolbar"
    :definitions="definitions"
  />
</template>

<script setup>
// The same limited toolbar as the post editor: it matches what the server keeps when it sanitizes HTML.
const model = defineModel({ type: String, default: '' })

const toolbar = [
  ['bold', 'italic'],
  ['h2', 'h3'],
  ['unordered', 'ordered'],
  ['quote'],
  ['link'],
  ['removeFormat'],
]
const definitions = {
  h2: { tip: 'h2', label: 'H2', cmd: 'formatBlock', param: 'H2' },
  h3: { tip: 'h3', label: 'H3', cmd: 'formatBlock', param: 'H3' },
}
</script>
