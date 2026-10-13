<template>
  <div>
    <label v-if="label" class="field-label" :for="id">{{ label }}</label>
    <q-input
      :id="id"
      :model-value="modelValue"
      :type="inputType"
      :dir="dir"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :error="!!error"
      :error-message="error"
      :inputmode="inputmode"
      :maxlength="maxlength"
      outlined
      hide-bottom-space
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <template v-if="type === 'password'" #prepend>
        <q-icon
          :name="visible ? 'visibility_off' : 'visibility'"
          class="cursor-pointer muted"
          :aria-label="$t('common.togglePassword')"
          @click="visible = !visible"
        />
      </template>
    </q-input>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  // Latin-script fields (email, password, code) are typed left-to-right
  dir: { type: String, default: undefined },
  autocomplete: { type: String, default: undefined },
  placeholder: { type: String, default: undefined },
  error: { type: String, default: '' },
  inputmode: { type: String, default: undefined },
  maxlength: { type: [String, Number], default: undefined },
})
defineEmits(['update:modelValue'])

const id = `field-${Math.random().toString(36).slice(2, 8)}`
const visible = ref(false)
const inputType = computed(() => (props.type === 'password' && visible.value ? 'text' : props.type))
</script>
