<template lang="pug">
q-form.text-center(@submit="onSubmit")
  addresses-select(
    :required="true"
    :addresses="addresses"
    :title="$t('secondForm.pickupLocation')"
    :addBtnLabel="$t('secondForm.updateAddress')"
    addressable_type="Customer"
    :addressable_id="customer.id"
    :selected="state.address"
    @selected="onAddressSelected"
    @addressSaved="onAddressSaved"
  )
  .text-primaryRed.q-px-md(
    v-if="!!(v$.address && v$.address.$errors && v$.address.$errors.length)"
    v-text="$t('validation.' + v$.address.$errors[0].$validator, v$.address.$errors[0].$params)"
  )

  .text-left.q-mt-lg.q-mb-md {{ $t('secondForm.selectShipping') }}
  .flex.justify-start.items-center
    .velo-returns-delivery-option.bg-gray.rounded-borders.text-left.q-pa-md.q-mr-md(
      :class="{ selected: state.deliveryType === 'return' }"
      @click="state.deliveryType = 'return'"
    )
      .flex.justify-between.items-end
        .text-bold {{ $t('secondForm.returnTitle') }}
        velo-icon.q-pb-md(
          name="returnDelivery"
          size="16px"
        )
      div {{ $t('secondForm.returnText') }}
    .velo-returns-delivery-option.bg-gray.rounded-borders.text-left.q-pa-md.q-mr-md(
      :class="{ selected: state.deliveryType === 'replacement' }"
      @click="state.deliveryType = 'replacement'"
    )
      .flex.justify-between.items-end
        .text-bold {{ $t('secondForm.replaceTitle') }}
        velo-icon.q-pb-md(
          name="replacementDelivery"
          size="16px"
        )
      div {{ $t('secondForm.replaceText') }}

  .text-left.q-mt-lg.q-mb-md {{ $t('secondForm.addExplanation') }}
  q-input.rounded-filled-input.tall-input.q-mb-lg(
    filled
    type="textarea"
    v-model="state.description"
    :placeholder="$t('secondForm.explanationPlaceholder')"
    :error="!!v$?.description?.$errors.length"
    :error-message="!!v$?.description?.$errors.length ? $t('validation.' + v$.description.$errors[0].$validator, v$.description.$errors[0].$params) : ''"
  )

  q-btn.full-width.velo-form-btn(
    unelevated
    no-caps
    type="submit"
    color="primaryBlue"
    :label="$t('secondForm.cta')"
  )
</template>
<script>
import { defineComponent, reactive, computed, onMounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required } from '@vuelidate/validators'
import { useVentiStore } from 'src/stores/venti'
import { useRouter } from 'vue-router'
import AddressesSelect from 'src/components/addresses/Select.vue'

export default defineComponent({
  name: 'PageDeliverySelect',

  components: {
    AddressesSelect
  },

  setup () {
    const ventiStore = useVentiStore()
    const router = useRouter()

    const customer = computed(() => ventiStore.customer)

    function onAddressSelected (address) {
      state.address = address
    }

    function onAddressSaved (address) {
      address.id = new Date().getTime()
      ventiStore.addresses.push(address)
      state.address = address
    }

    const state = reactive({
      address: ventiStore.call.customer_address ? ventiStore.call.customer_address : ventiStore.order.shipping_address,
      deliveryType: ventiStore.call.is_replacement ? 'replacement' : 'return',
      description: ventiStore.call.description
    })

    const rules = computed(() => {
      return {
        address: { required },
        deliveryType: { required },
        description: { required }
      }
    })

    const v$ = useVuelidate(rules, state)

    async function onSubmit () {
      v$.value.$touch()
      if (!v$.value.$invalid) {
        ventiStore.checkReturnInfo(state).then(({ success, result }) => {
          if (success) {
            router.push({ name: 'summary' })
          }
        })
      }
    }

    const addresses = computed(() => ventiStore.addresses)

    onMounted(() => {
      if (!Object.keys(ventiStore.order).length) {
        router.push({ name: 'findOrder' })
      }
    })

    return {
      addresses,
      customer,
      v$,
      state,
      onAddressSelected,
      onAddressSaved,
      onSubmit
    }
  }
})
</script>

<style lang="sass" scoped>
.velo-returns-delivery-option
  cursor: pointer
  width: 150px
  height: 100px
  border: solid 2px $gray
  transition: border-color 300ms ease-in-out
  &.selected
    border-color: $secondaryBlue
</style>
