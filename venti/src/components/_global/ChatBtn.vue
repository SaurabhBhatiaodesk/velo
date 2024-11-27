<template lang="pug">
a(
  href="https://api.whatsapp.com/send/?phone=972538255518"
  target="_blank"
)
  q-btn.chat-toggle-btn.relative-position.q-ma-md.q-py-none.bg-primaryBlue(
    :ripple="false"
    dense
  )
    velo-icon(
      color="white"
      name="chat"
      size="2.5em"
    )
      template(#tooltip)
        q-tooltip.bg-primaryBlue.text-weight-medium {{ $t('app.chatTooltip') }}
    //- transition(
    //-   appear
    //-   enter-active-class="animated slideInRight"
    //-   leave-active-class="animated slideOutRight"
    //- )
    //-   .chat-wrap(
    //-     v-show="chatVisible"
    //-   )
    //-     velo-icon.absolute-top-right.q-pa-md(
    //-       name="close"
    //-       color="white"
    //-       cursor="pointer"
    //-       @click.stop="toggleChat"
    //-     )
    //-     iframe.fit(src="https://tawk.to/chat/645b61a4ad80445890ec2125/1h02fared")
</template>
<script>
import { defineComponent, ref } from 'vue'

export default defineComponent({
  name: 'ChatBtn',

  setup () {
    const chatVisible = ref(false)

    function toggleChat () {
      chatVisible.value = !chatVisible.value
    }

    return {
      chatVisible,
      toggleChat
    }
  }
})
</script>
<style lang="sass" scoped>
.chat-toggle-btn
  border-radius: 100%
  width: 55px
  height: 55px
.chat-wrap
  height: 500px
  width: 300px
  position: absolute
  bottom: 60px
  right: 0
.chat-wrap iframe
  border: none
</style>
