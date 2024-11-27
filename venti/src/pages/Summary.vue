<template lang="pug">
.text-center(v-if="order && Object.keys(order).length")
  .text-h5.text-weight-bolder.q-my-lg {{ $t('summary.title') }}
  q-list.q-mb-lg(
    bordered
    separator
  )
    q-item.flex.justify-between.items-center
      .text-left.text-bold {{ $t('summary.orderNumber', { deliveryType: $t('summary.' + deliveryType) }) }}
      .text-right {{ order.name }}
    q-item.flex.justify-between.items-center
      .text-left.text-bold {{ $t('summary.deliveryType') }}
      .text-right {{ call.is_replacement ? $t('summary.replacement') : $t('summary.return') }}
    q-item.flex.justify-between.items-center
        .text-left.text-bold {{ $t('summary.address') }}
        .text-right {{ displayAddress(call.customer_address) }}
    q-item.flex.justify-between.items-center
        .text-left.text-bold {{ $t('summary.recepient') }}
        .text-right {{ call.customer_address.first_name }} {{ call.customer_address.last_name }}
    q-item.flex.justify-between.items-center
      .text-left.text-bold {{ $t('summary.phone') }}
      .text-right {{ call.customer_address.phone }}
    q-item.flex.justify-between.items-center
      .text-left.text-bold {{ $t('summary.description', { deliveryType: $t('summary.' + deliveryType) }) }}
      .text-right {{ call.description }}
    q-item.flex.justify-between.items-center(v-if="settings.charge")
      .text-left.text-bold {{ $t('summary.price', { deliveryType: $t('summary.' + deliveryType) }) }}
      .text-right {{ call.is_replacement ? settings.replacementRate : settings.returnRate }}

  .text-weight-bolder.q-mb-md {{ $t('summary.review') }}

  .flex.justify-center.items-center
    q-btn.q-mx-xs(
      no-caps
      color="primaryBlue"
      :label="settings.charge ? $t('summary.pay') : $t('summary.confirm')"
      @click="confirm"
    )
    q-btn.q-mx-xs(
      no-caps
      color="primaryRed"
      :label="$t('summary.cancel')"
      @click="$router.push({ name: 'deliverySelect' })"
    )
</template>
<script>
import { defineComponent, onMounted, computed } from 'vue'
import { useVentiStore } from 'src/stores/venti'
import { useRouter } from 'vue-router'
import { displayAddress } from 'src/composables/display_address'

export default defineComponent({
  name: 'PageSummary',

  setup () {
    const ventiStore = useVentiStore()
    const router = useRouter()
    const call = computed(() => ventiStore.call)
    const deliveryType = computed(() => call.value.is_replacement ? 'replacement' : 'return')
    const settings = computed(() => ventiStore.settings)
    const order = computed(() => ventiStore.order)
    const customer = computed(() => ventiStore.customer)

    function confirm () {
      router.push({ name: (settings.value.charge) ? 'payment' : 'thankYou' })
    }

    onMounted(() => {
      if (!Object.keys(ventiStore.order).length) {
        router.push({ name: 'findOrder' })
      }
    })

    return {
      call,
      deliveryType,
      order,
      customer,
      settings,
      confirm,
      displayAddress
    }
  }
})
</script>
<style lang="sass" scoped>
.thumbs-up
  width: 30%
  max-width: 150px
</style>
