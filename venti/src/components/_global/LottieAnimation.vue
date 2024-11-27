<template lang="pug">
div(
  v-if="style"
  :style="style"
  ref="lavContainer"
)
</template>
<script>
import lottie from 'lottie-web'
import axios from 'axios'
import { defineComponent, onMounted, watch, ref } from 'vue'

export default defineComponent({
  name: 'LottieAnimation',

  props: {
    path: {
      required: true
    },
    speed: {
      type: Number,
      required: false,
      default: 1
    },
    width: {
      type: Number,
      required: false,
      default: -1
    },
    height: {
      type: Number,
      required: false,
      default: -1
    },
    loop: {
      type: Boolean,
      required: false,
      default: true
    },
    autoPlay: {
      type: Boolean,
      required: false,
      default: true
    },
    loopDelayMin: {
      type: Number,
      required: false,
      default: 0
    },
    loopDelayMax: {
      type: Number,
      required: false,
      default: 0
    }
  },

  setup (props, { emit }) {
    const anim = ref(false)
    const style = ref(null)
    const lavContainer = ref(null)

    async function loadJsonData (path) {
      const response = await axios.get('/' + path)
      return response.data
    }

    function getRandomInt (min, max) {
      min = Math.ceil(min)
      max = Math.floor(max)
      return Math.floor(Math.random() * (max - min)) + min // The maximum is exclusive and the minimum is inclusive
    }

    function executeLoop () {
      anim.value.play()
      setTimeout(() => {
        anim.value.stop()
        executeLoop()
      }, getRandomInt(props.loopDelayMin, (props.loopDelayMax === 0) ? props.loopDelayMin : props.loopDelayMax))
    }

    async function init () {
      style.value = {
        width: (props.width !== -1) ? `${props.width}px` : '100%',
        height: (props.height !== -1) ? `${props.height}px` : '100%',
        overflow: 'hidden',
        margin: '0 auto'
      }
      const jsonData = await loadJsonData(props.path)
      if (anim.value) {
        anim.value.destroy() // Releases resources. The DOM element will be emptied.
      }
      anim.value = lottie.loadAnimation({
        container: lavContainer.value,
        renderer: 'svg',
        loop: props.loop,
        autoplay: props.autoPlay,
        animationData: jsonData,
        rendererSettings: {
          scaleMode: 'centerCrop',
          clearCanvas: true,
          progressiveLoad: false,
          hideOnTransparent: true
        }
      })
      emit('AnimControl', anim.value)
      anim.value.setSpeed(props.speed)
      if (props.loopDelayMin > 0) {
        anim.value.loop = false
        anim.value.autoplay = false
        executeLoop()
      }
    }

    onMounted(init)
    watch(() => props.path, init)

    return {
      lavContainer,
      style
    }
  }
})
</script>
