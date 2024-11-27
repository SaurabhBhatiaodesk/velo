<template lang="pug">
popup-dialog(
  maximized
  :show="showDialog"
  @closed="showDialog = false"
)
  address-form(
    v-if="Object.keys(currentAddress).length > 0"
    :loading="saving"
    :formState="currentAddress"
    @success="onAddressFormSuccess"
  )

.col-12.flex.justify-between.items-center.q-mb-md(v-if="title && title.length")
  div {{ title }}
  velo-btn(
    icon="add"
    :label="addBtnLabel.length ? addBtnLabel : $t('addresses.list.addBtn')"
    @click="addAddress"
  )

.col-12.flex.justify-between.items-center
  .full-width.flex.justify-between.items-center.text-h6.q-mb-lg(v-if="!addresses || !addresses.length")
    div {{ emptyLabel.length ? emptyLabel : $t('addresses.list.empty') }}
    velo-btn(
      icon="add"
      :label="addBtnLabel.length ? addBtnLabel : $t('addresses.list.addBtn')"
      @click="addAddress"
    )
  q-select.full-width.q-mb-md(
    v-else
    filled
    :class="{ required }"
    :loading="loading"
    :options="addresses"
    :model-value="selected"
    @update:model-value="selectAddress"
  )
    template(#label)
      span(v-if="selectLabel && selectLabel.length") {{ selectLabel }}
    template(#selected)
      q-item-label.q-px-md {{ (selected && selected.id) ? displayAddress(selected, true) : '' }}
    template(#option="{ opt, itemProps }")
      q-item(v-bind="itemProps")
        q-item-section
          q-item-label {{ displayAddress(opt, true) }}
    template(#after-options)
      q-item(
        clickable
        v-if="showAddOption"
        @click="addAddress"
      )
        q-item-section
          q-item-label {{ $t('addresses.list.addBtn') }}
</template>
<script>
import AddressForm from 'src/components/addresses/Form.vue'
import { defineComponent, ref, watch } from 'vue'
import { displayAddress } from 'src/composables/display_address.js'

export default defineComponent({
  name: 'AddressSelect',

  components: {
    AddressForm
  },

  props: {
    addresses: {
      type: Object,
      required: true
    },
    title: {
      type: String,
      required: false,
      default: ''
    },
    addBtnLabel: {
      type: String,
      required: false,
      default: ''
    },
    emptyLabel: {
      type: String,
      required: false,
      default: ''
    },
    addressable_type: {
      type: String,
      required: true
    },
    addressable_id: {
      type: [Number, String],
      required: false,
      default: ''
    },
    formState: {
      type: Object,
      required: false,
      default: () => ({})
    },
    selected: {
      type: Object,
      required: false,
      default: () => ({})
    },
    loading: {
      type: Boolean,
      required: false,
      default: false
    },
    error: {
      type: String,
      required: false,
      default: ''
    },
    selectLabel: {
      type: String,
      required: false,
      default: ''
    },
    showAddOption: {
      type: Boolean,
      required: false,
      default: false
    },
    required: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'selected',
    'addressSaved'
  ],

  setup (props, { emit }) {
    const showDialog = ref(false)
    // const notifications = inject('Notifications')

    function selectAddress (address) {
      emit('selected', address)
    }

    const currentAddress = ref(props.selected)
    watch(() => props.selected, (first, second) => {
      currentAddress.value = props.selected
    })

    function addAddress () {
      if (Object.keys(props.formState).length) {
        currentAddress.value = {
          first_name: props.formState.first_name || '',
          last_name: props.formState.last_name || '',
          phone: props.formState.phone || '',
          state: props.formState.state || '',
          country: props.formState.country || ''
        }
      } else if (props.addresses.length) {
        currentAddress.value = {
          first_name: props.addresses[0].first_name || '',
          last_name: props.addresses[0].last_name || '',
          phone: props.addresses[0].phone || '',
          state: props.addresses[0].state || '',
          country: props.addresses[0].country || ''
        }
      }
      showDialog.value = true
    }

    const saving = ref(false)
    async function onAddressFormSuccess (address) {
      saving.value = true
      if (!props.onboarding) {
        address.addressable_type = props.addressable_type
        address[(isNaN(props.addressable_id)) ? 'addressable_slug' : 'addressable_id'] = props.addressable_id
        emit('addressSaved', address)
        emit('selected', address)
      }
      showDialog.value = false
      saving.value = false
    }

    return {
      selectAddress,
      showDialog,
      currentAddress,
      addAddress,
      onAddressFormSuccess,
      saving,
      displayAddress
    }
  }
})
</script>
<style lang="sass" scoped>
</style>
