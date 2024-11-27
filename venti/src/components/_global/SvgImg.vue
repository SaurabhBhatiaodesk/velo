<template lang="pug">
div(
  v-if="fill.length || cssFill || src.endsWith('.svg')"
  :title="alt ? alt : ''"
  :style="Object.assign((cssFill ? {} : { backgroundColor: fill }), {  width: size, height: size, maskSize: 'contain', maskPosition: 'center center',  maskRepeat: 'no-repeat', maskImage: 'url(' + realSrc + ')' })"
)
q-img(
  v-else
  :src="realSrc"
  :width="size"
  :height="size"
)
</template>
<script>
import { computed, defineComponent } from 'vue'
import { serverLink } from 'src/composables/server_urls'

export default defineComponent({
  props: {
    src: {
      type: String,
      required: true
    },
    alt: {
      type: String,
      default: ''
    },
    fill: {
      type: String,
      default: '#1D3557' // primaryBlue
    },
    size: {
      type: String,
      default: '1em'
    },
    cssFill: {
      type: Boolean,
      default: false
    }
  },

  setup (props) {
    const realSrc = computed(() => {
      if (props.src.startsWith('http') || props.src.startsWith('/src')) {
        return props.src
      }
      return serverLink(props.src)
    })

    return {
      realSrc
    }
  }
})
</script>
