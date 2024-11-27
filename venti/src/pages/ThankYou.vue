<template lang="pug">
.text-center
  q-avatar(
    :color="avatarColor"
    size="150px"
  )
    q-spinner-puff(
      v-if="loading"
      color="white"
    )
    img(
      v-else
      :class="orderCreated ? '' : 'flipped'"
      src="~assets/thumbs_up.svg"
    )
  .text-h5.text-weight-bolder.q-my-lg
    span(v-if="loading") {{ $t('thankYou.loading', { deliveryType: $t('thankYou.' + deliveryType) }) }}
    span(v-else-if="orderCreated") {{ $t('thankYou.success', { deliveryType: $t('thankYou.' + deliveryType) }) }}
    span(v-else) {{ $t('thankYou.failed') }}

.text-center.q-mb-sm(v-if="!loading && !orderCreated")
  q-btn(
    :label="$t('thankYou.retry')"
    color="primaryBlue"
    @click="createOrder"
  )
.text-left(v-if="orderCreated") {{ $t('thankYou.text', { deliveryType: $t('thankYou.' + deliveryType) }) }}
</template>
<script>
import { defineComponent, onMounted, ref, computed } from 'vue'
import { useVentiStore } from 'src/stores/venti'

export default defineComponent({
  name: 'PageThankYou',

  setup () {
    const ventiStore = useVentiStore()
    const loading = ref(true)
    const orderCreated = ref(false)
    const avatarColor = computed(() => {
      if (loading.value) {
        return 'primaryBlue'
      }
      return orderCreated.value ? 'secondaryGreen' : 'secondaryRed'
    })

    const deliveryType = computed(() => {
      return ventiStore.call.is_replacement ? 'replacement' : 'return'
    })

    async function createOrder () {
      loading.value = true
      await ventiStore.createOrder().then(({ success, result }) => {
        if (success) {
          orderCreated.value = true
        }
      })
      loading.value = false
    }

    onMounted(() => {
      createOrder()
    })

    return {
      ventiStore,
      loading,
      orderCreated,
      avatarColor,
      deliveryType,
      createOrder
    }
  }
})
</script>
<style lang="sass">
.flipped
  transform: rotateZ(180deg)
</style>
