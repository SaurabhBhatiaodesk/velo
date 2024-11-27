<template lang="pug">
q-avatar(
  color="primaryBlue"
  text-color="white"
  font-size="21px"
)
  .text-weight-bolder(v-if="!person.image") {{ label }}
  svg-img(
    v-else
    :src="person.image"
    :alt="person.first_name + ' ' + person.last_name"
  )
</template>
<script>
import { defineComponent, computed } from 'vue'

export default defineComponent({
  name: 'PersonAvatar',

  props: {
    person: {
      type: Object,
      required: true
    }
  },

  setup (props) {
    const label = computed(() => {
      let res = ''
      if (props.person.first_name && props.person.first_name.length) {
        res += props.person.first_name[0]
      }
      if (props.person.last_name && props.person.last_name.length) {
        res += props.person.last_name[0]
      }
      if (res.length) {
        return res
      }
      if (props.person.email && props.person.email.length) {
        return props.person.email[0]
      }
      return '?'
    })

    return {
      label
    }
  }
})
</script>
