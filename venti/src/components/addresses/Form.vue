<template lang="pug">
q-form.row.fit.q-pt-none(@submit="onSubmit")
  .col-12.flex.justify-between.q-px-md.q-pb-xl
    .text-h6.text-weight-bolder(v-if="title.length") {{ title }}
    .text-h6.text-weight-bolder(v-else-if="state.id") {{ $t('addresses.form.edit') }}
    .text-h6.text-weight-bolder(v-else) {{ $t('addresses.form.create') }}
  .row.col-12.col-sm-6.col-lg-8
    .col-12.col-lg-6.relative-position.q-px-md
      .text-bold.q-mb-md {{ $t('addresses.form.contactInfo') }}
      q-input.required(
        filled
        v-model="state.first_name"
        :disable="loading"
        :label="$t('addresses.form.first_name')"
        :error="!!(v$.first_name && v$.first_name.$errors && v$.first_name.$errors.length)"
        :error-message="!!(v$.first_name && v$.first_name.$errors && v$.first_name.$errors.length) ? $t('validation.' + v$.first_name.$errors[0].$validator, v$.first_name.$errors[0].$params) : ''"
      )

      q-input.required(
        filled
        v-model="state.last_name"
        :disable="loading"
        :label="$t('addresses.form.last_name')"
        :error="!!(v$.last_name && v$.last_name.$errors && v$.last_name.$errors.length)"
        :error-message="!!(v$.last_name && v$.last_name.$errors && v$.last_name.$errors.length) ? $t('validation.' + v$.last_name.$errors[0].$validator, v$.last_name.$errors[0].$params) : ''"
      )

      q-input.required(
        filled
        v-model="state.phone"
        :disable="loading"
        :label="$t('addresses.form.phone')"
        :error="!!(v$.phone && v$.phone.$errors && v$.phone.$errors.length)"
        :error-message="!!(v$.phone && v$.phone.$errors && v$.phone.$errors.length) ? $t('validation.' + v$.phone.$errors[0].$validator, v$.phone.$errors[0].$params) : ''"
        @update:model-value="state.phone = state.phone.replace(/[^0-9]/g, '')"
      )

      q-input(
        filled
        v-model="state.company_name"
        :disable="loading"
        :label="$t('addresses.form.company_name')"
        :error="!!(v$.company_name && v$.company_name.$errors && v$.company_name.$errors.length)"
        :error-message="!!(v$.company_name && v$.company_name.$errors && v$.company_name.$errors.length) ? $t('validation.' + v$.company_name.$errors[0].$validator, v$.company_name.$errors[0].$params) : ''"
      )

      q-input(
        filled
        type="number"
        v-model="state.tax_id"
        :disable="loading"
        :label="$t('addresses.form.tax_id')"
        :error="!!(v$.tax_id && v$.tax_id.$errors && v$.tax_id.$errors.length)"
        :error-message="!!(v$.tax_id && v$.tax_id.$errors && v$.tax_id.$errors.length) ? $t('validation.' + v$.tax_id.$errors[0].$validator, v$.tax_id.$errors[0].$params) : ''"
      )

      .address-form-separator.absolute-right
    .col-12.col-lg-6.relative-position.q-px-md
      .text-bold.q-mb-md {{ $t('addresses.form.locationInfo') }}
      q-select.required(
        filled
        v-model="state.country"
        borderless
        emit-value
        map-options
        :options="countryOptions"
        :disable="loading"
        :label="$t('addresses.form.country')"
        :error="!!(v$.country && v$.country.$errors && v$.country.$errors.length)"
        :error-message="!!(v$.country && v$.country.$errors && v$.country.$errors.length) ? $t('validation.' + v$.country.$errors[0].$validator, v$.country.$errors[0].$params) : ''"
        @update:model-value="onCountrySelect"
      )

      .row
        q-input.required.col-8.gmaps-autocomplete-input.q-pr-md(
          filled
          v-model="state.street"
          :disable="loading"
          :label="$t('addresses.form.street')"
          :error="!!address1Error.length"
          :error-message="!!address1Error.length ? $t('validation.' + address1Error) : ''"
          @update:model-value="(val) => { state.line1 = val + ' ' + state.houseNumber }"
        )

        q-input.required.col-4(
          filled
          v-model="state.houseNumber"
          :disable="loading"
          :label="$t('addresses.form.houseNumber')"
          :error="!!(v$.houseNumber && v$.houseNumber.$errors && v$.houseNumber.$errors.length)"
          :error-message="!!(v$.houseNumber && v$.houseNumber.$errors && v$.houseNumber.$errors.length) ? $t('validation.' + v$.houseNumber.$errors[0].$validator, v$.houseNumber.$errors[0].$params) : ''"
          @update:model-value="(val) => { state.line1 = state.street + ' ' + val }"
        )

        template(v-if="displayAddress(state).length")
          .text-bold.q-pr-xs {{ $t('addresses.form.addressResult') }}
          .address-completion.q-pb-lg {{ displayAddress(state, true, true) }}

      q-input(
        filled
        v-model="state.line2"
        :disable="loading"
        :label="$t('addresses.form.line2')"
        :error="!!(v$.line2 && v$.line2.$errors && v$.line2.$errors.length)"
        :error-message="!!(v$.line2 && v$.line2.$errors && v$.line2.$errors.length) ? $t('validation.' + v$.line2.$errors[0].$validator, v$.line2.$errors[0].$params) : ''"
      )

      q-input(
        filled
        type="number"
        v-model="state.zipcode"
        :disable="loading"
        :label="$t('addresses.form.zipcode')"
        :error="!!(v$.zipcode && v$.zipcode.$errors && v$.zipcode.$errors.length)"
        :error-message="!!(v$.zipcode && v$.zipcode.$errors && v$.zipcode.$errors.length) ? $t('validation.' + v$.zipcode.$errors[0].$validator, v$.zipcode.$errors[0].$params) : ''"
      )

      .address-form-separator.absolute-right
  .col-12.col-sm-6.col-lg-4.flex.relative-position.q-px-md
    div.full-width
      .relative-position.q-mb-md
        loading-overlay(v-if="mapLoading")
        .text-bold.q-mb-md {{ $t('addresses.form.mapTitle') }}
        .gmaps-map-container
  .col-12.q-px-md

  .col-12.flex.justify-end.q-px-md
    q-btn(
      no-caps
      flat
      type="submit"
      color="primary"
      :loading="loading"
      :label="$t('addresses.form.submit')"
    )
</template>
<script>
import { defineComponent, onMounted, reactive, computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import { required, numeric } from '@vuelidate/validators'
import { countries, zones } from 'moment-timezone/data/meta/latest.json'
import { formatGmapsPlace, loadGmapsApi, getGmapsCountry } from 'src/composables/gmaps_interface'
import { displayAddress } from 'src/composables/display_address'

export default defineComponent({
  name: 'AddressForm',

  props: {
    formState: {
      type: Object,
      required: false,
      default: () => ({})
    },
    title: {
      type: String,
      required: false,
      default: ''
    },
    loading: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  setup (props, { emit }) {
    const { alphaDashApostrophe } = inject('Validators')
    const { negative } = inject('Notifications')
    const { t } = useI18n()

    function getCountry (property) {
      const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
      for (const zone in zones) {
        if (timezone === zone) {
          return countries[zones[zone].countries[0]][property]
        }
      }
      return ''
    }

    const state = reactive({})
    function makeState (initialState = {}) {
      const emptyState = {
        first_name: '',
        last_name: '',
        street: '',
        houseNumber: '',
        line1: '',
        line2: '',
        zipcode: '',
        city: '',
        state: '',
        country: '',
        phone: '',
        tax_id: '',
        company_name: '',
        latitude: '',
        longitude: ''
      }

      for (const i in emptyState) {
        if (Object.prototype.hasOwnProperty.call(initialState, i) && (!isNaN(initialState[i]) || initialState[i].length)) {
          state[i] = initialState[i]
        } else if (Object.prototype.hasOwnProperty.call(props.formState, i) && props.formState[i]) {
          state[i] = props.formState[i]
        } else {
          state[i] = emptyState[i]
        }
      }

      if (state.line1.length && !state.street.length) {
        state.houseNumber = state.line1.match(/(\d[\d.]*)/g)
        state.houseNumber = (state.houseNumber === null) ? '' : state.houseNumber.join(' ')
        state.street = state.line1.replace(/[0-9]/g, '')
      }

      if (props.formState.id) {
        state.id = props.formState.id
      }
      if (!state.country.length) {
        state.country = getCountry('name')
      }
    }

    const autocomplete = ref({})
    const geocoder = ref({})
    const map = ref({})
    const gMaps = ref({})
    const mapLoading = ref(true)
    const marker = ref({})
    function initGmaps () {
      loadGmapsApi().then(({ maps }) => {
        gMaps.value = maps
        const center = (props.formState.latitude && props.formState.longitude) ? {
          lat: parseFloat(props.formState.latitude),
          lng: parseFloat(props.formState.longitude)
        } : {
          // default to tel aviv
          lat: 32.0853,
          lng: 34.7818
        }
        map.value = new gMaps.value.Map(document.querySelector('.gmaps-map-container'), {
          clickableIcons: false,
          disableDefaultUI: true,
          keyboardShortcuts: false,
          zoom: 12,
          center
        })

        marker.value = new gMaps.value.Marker({
          map: map.value,
          position: center,
          draggable: false
        })

        mapLoading.value = false
        geocoder.value = new gMaps.value.Geocoder()

        const input = document.querySelector('.gmaps-autocomplete-input input')
        const options = {
          fields: ['address_components', 'geometry.location']
        }

        const country = getGmapsCountry(state.country)
        if (country.length) {
          options.componentRestrictions = { country }
        }
        autocomplete.value = new gMaps.value.places.Autocomplete(input, options)
        autocomplete.value.addListener('place_changed', () => {
          const place = autocomplete.value.getPlace()
          if (!place.geometry || !place.geometry.location) {
            negative(t('addresses.invalidAutocomplete'))
          } else {
            marker.value.setMap(null)
            marker.value = new gMaps.value.Marker({
              map: map.value,
              position: place.geometry.location,
              draggable: false
            })
            makeState(Object.assign(state, formatGmapsPlace(place)))
            map.value.setCenter(new gMaps.value.LatLng(state.latitude, state.longitude))
            const country = getGmapsCountry(state.country)
            if (country.length) {
              autocomplete.value.setComponentRestrictions({ country })
            }
          }
        })
      })
    }

    onMounted(() => {
      makeState()
      initGmaps()
    })

    const rules = computed(() => {
      return {
        first_name: { required, alphaDashApostrophe },
        last_name: { required, alphaDashApostrophe },
        street: { required },
        houseNumber: { required },
        line2: {},
        zipcode: { numeric },
        city: { required },
        state: { alphaDashApostrophe },
        country: { required, alphaDashApostrophe },
        phone: { required, numeric },
        tax_id: { numeric },
        company_name: { alphaDashApostrophe }
      }
    })

    const v$ = useVuelidate(rules, state)

    function onLocated ({ initial, result }) {
      if (!result.city.length) {
        negative(t('addresses.geolocationFailed'))
      } else {
        mapLoading.value = false
        if (!(initial && Object.keys(props.formState).length)) {
          for (const i in result) {
            state[i] = result[i]
          }
          const position = new gMaps.value.LatLng(result.latitude, result.longitude)
          map.value.setCenter(position)
          marker.value.setMap(null)
          marker.value = new gMaps.value.Marker({
            map: map.value,
            position,
            draggable: false
          })
        }
      }
    }

    async function onSubmit () {
      v$.value.$touch()
      if (!v$.value.$invalid) {
        const result = JSON.parse(JSON.stringify(state))
        emit('success', result)
      }
    }

    const countryOptions = ref([])
    function getCountryOptions (needle = '') {
      countryOptions.value = []
      needle = needle.toLowerCase()
      for (const i in countries) {
        if (countries[i].name.toLowerCase().indexOf(needle) === 0) {
          countryOptions.value.push(countries[i].name)
        }
      }
    }
    getCountryOptions()

    async function onCountrySelect (selectedCountry) {
      const country = getGmapsCountry(selectedCountry)
      mapLoading.value = false
      await geocoder.value.geocode({ address: selectedCountry }, function (results, status) {
        if (status === gMaps.value.GeocoderStatus.OK) {
          map.value.setCenter(results[0].geometry.location)
          makeState({ country: selectedCountry })
        }
      })
      if (country.length) {
        autocomplete.value.setComponentRestrictions({ country })
      }
    }

    function filterFn (val, update, abort) {
      update(() => {
        getCountryOptions(val)
      })
    }

    const address1Error = computed(() => {
      const inputs = {
        street: 'invalidAddress',
        city: 'invalidCity',
        state: 'invalidState',
        country: 'invalidCountry'
      }
      for (const input in inputs) {
        if (v$.value[input] && v$.value[input].$errors && v$.value[input].$errors.length) {
          return inputs[input]
        }
      }

      return ''
    })

    return {
      v$,
      state,
      onLocated,
      onSubmit,
      countryOptions,
      onCountrySelect,
      filterFn,
      address1Error,
      displayAddress,
      mapLoading
    }
  }
})
</script>
<style lang="sass" scoped>
.address-completion
  font-size: 1.1em
.address-form-separator
  width: 1px
  height: 398px
  background: $mediumLightGray
.gmaps-map-container
  width: 100%
  height: 600px
@media screen and (min-width: $breakpoint-lg-min)
  .gmaps-map-container
    height: 337px
@media screen and (max-width: $breakpoint-xs-max)
  .address-form-separator
    display: none
</style>
