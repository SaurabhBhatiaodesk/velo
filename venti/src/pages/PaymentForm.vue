<template lang="pug">
q-form.relative-position(@submit="onSubmit")
  loading-overlay(v-if="loading || !initialized")
  q-card-section
    .q-px-sm
      .text-h6.text-weight-bolder {{ $t('paymentMethods.form.title') }}
      .q-pt-md {{ $t('paymentMethods.form.subtitle') }}
  q-card-section.row
    .col-12.col-md-6.q-px-sm
      q-input.required(
        filled
        v-model="state.name"
        :disable="!initialized"
        :label="$t('paymentMethods.form.name')"
        :error="!!(v$.name && v$.name.$errors && v$.name.$errors.length)"
        :error-message="!!(v$.name && v$.name.$errors && v$.name.$errors.length) ? $t('validation.' + v$.name.$errors[0].$validator, v$.name.$errors[0].$params) : ''"
        @focus="focusHelper = ''"
      )
    .col-12.col-md-6.q-px-sm
      q-input.required(
        filled
        type="number"
        v-model="state.id"
        :disable="!initialized"
        :label="$t('paymentMethods.form.id')"
        :error="!!(v$.id && v$.id.$errors && v$.id.$errors.length)"
        @focus="focusHelper = ''"
        :error-message="!!(v$.id && v$.id.$errors && v$.id.$errors.length) ? $t('validation.' + v$.id.$errors[0].$validator, v$.id.$errors[0].$params) : ''"
      )
    .col-12.col-md-6.q-px-sm
      q-input.required(
        filled
        v-model="state.email"
        :disable="!initialized"
        :label="$t('paymentMethods.form.email')"
        :error="!!(v$.email && v$.email.$errors && v$.email.$errors.length)"
        :error-message="!!(v$.email && v$.email.$errors && v$.email.$errors.length) ? $t('validation.' + v$.email.$errors[0].$validator, v$.email.$errors[0].$params) : ''"
        @focus="focusHelper = ''"
      )
    .col-12.col-md-6.q-px-sm
      q-input.required(
        filled
        v-model="state.phone"
        :disable="!initialized"
        :label="$t('paymentMethods.form.phone')"
        :error="!!(v$.phone && v$.phone.$errors && v$.phone.$errors.length)"
        :error-message="!!(v$.phone && v$.phone.$errors && v$.phone.$errors.length) ? $t('validation.' + v$.phone.$errors[0].$validator, v$.phone.$errors[0].$params) : ''"
        @focus="focusHelper = ''"
        @update:model-value="state.phone = state.phone.replace(/[^0-9]/g, '')"
      )

    .col-12.q-px-sm
      q-field.required(
        filled
        ref="number"
        :class="{ 'q-field--focused q-field--highlighted q-field--float': (focusHelper === 'number'), 'q-field--float': touchedHelper.number }"
        :disable="!initialized"
        :error="errorsHelper.number"
        :label="$t('paymentMethods.form.number')"
      )
        #card-number.payme-target
    .col-12.col-md-4.q-px-sm
      q-field.required(
        filled
        ref="expiry"
        :class="{ 'q-field--focused q-field--highlighted q-field--float': (focusHelper === 'expiry'), 'q-field--float': touchedHelper.expiry }"
        :disable="!initialized"
        :label="$t('paymentMethods.form.expiry')"
        :error="errorsHelper.expiry"
        @focus="focusHelper = 'expiry'"
      )
        #card-expiry.payme-target
    .col-12.col-md-4.q-px-sm
      q-field.required(
        filled
        ref="cvv"
        :class="{ 'q-field--focused q-field--highlighted q-field--float': (focusHelper === 'cvv'), 'q-field--float': touchedHelper.cvv }"
        :disable="!initialized"
        :label="$t('paymentMethods.form.cvv')"
        :error="errorsHelper.cvv"
        @focus="focusHelper = 'cvv'"
      )
        #card-cvv.payme-target
    .col-6.q-px-sm

    .col-12
      q-field(
        borderless
        :error="!!(v$.terms && v$.terms.$errors && v$.terms.$errors.length)"
        :error-message="!!(v$.terms && v$.terms.$errors && v$.terms.$errors.length) ? $t('validation.' + v$.terms.$errors[0].$validator, v$.terms.$errors[0].$params) : ''"
      )
        q-checkbox(
          v-model="state.terms"
          color="primaryBlue"
        )
          span {{ $t('paymentMethods.form.termsCheckbox') }}
          a.q-px-xs(
            href="https://app.veloapp.io/static/privacy"
            target="_blank"
          ) {{ $t('paymentMethods.form.privacy') }}
          span {{ $t('paymentMethods.form.and') }}
          a.q-px-xs(
            href="https://app.veloapp.io/static/terms"
            target="_blank"
          ) {{ $t('paymentMethods.form.terms') }}

  q-card-actions.flex-center
    .col-12.text-center.q-mb-md
      .text-bold {{ $t('paymentMethods.form.total', { total: formattedTotal }) }}

    q-btn.velo-form-btn.col-12.bg-primaryBlue.text-white.text-weight-bolder(
      unelevated
      no-caps
      type="submit"
      :label="$t('paymentMethods.form.cta', { total: formattedTotal })"
    )
</template>
<script>
import { defineComponent, onMounted, reactive, computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { required, numeric, sameAs } from '@vuelidate/validators'
import { useVentiStore } from 'src/stores/venti'

export default defineComponent({
  name: 'PaymentForm',

  emits: [
    'success'
  ],

  setup (props, { emit }) {
    const { locale, t } = useI18n()
    const { alphaDash } = inject('Validators')
    const notifications = inject('Notifications')
    const ventiStore = useVentiStore()
    const label = computed(() => (!Object.keys(ventiStore.order).length) ? '' : t(`secondForm.${ventiStore.call.is_replacement ? 'replace' : 'return'}Title`))
    const total = computed(() => (!Object.keys(ventiStore.order).length) ? 0 : parseFloat(ventiStore.settings[ventiStore.call.is_replacement ? 'replacementRate' : 'returnRate']))
    const formattedTotal = computed(() => (!Object.keys(ventiStore.order).length) ? '' : (total.value.toFixed(2) + ventiStore.settings.currency.symbol))
    const focusHelper = ref('')
    const touchedHelper = reactive({
      number: false,
      expiry: false,
      cvv: false
    })
    const errorsHelper = reactive({
      number: false,
      expiry: false,
      cvv: false
    })

    const state = reactive({
      name: ventiStore.customer.first_name + ' ' + ventiStore.customer.last_name,
      phone: ventiStore.customer.phone,
      email: ventiStore.customer.email,
      id: '',
      promoCode: '',
      terms: false
    })

    const rules = computed(() => {
      return {
        name: { required, alphaDash },
        id: { required, numeric },
        terms: { required, mandatory: sameAs(true) }
      }
    })

    const v$ = useVuelidate(rules, state)

    /* global PayMe */
    const initialized = ref(false)

    // template refs
    const number = ref(null)
    const expiry = ref(null)
    const cvv = ref(null)

    const paymeInstance = ref(null)
    function initPayme () {
      return new Promise(resolve => {
        PayMe.create(process.env.PAYME_API_KEY, {
          testMode: !!process.env.DEBUG,
          language: locale.value.split('-')[0],
          tokenIsPermanent: true
        }).then(instance => {
          paymeInstance.value = instance
          const fields = paymeInstance.value.hostedFields()
          const fieldOptions = (fieldName) => ({
            messages: {
              required: t('validation.required'),
              invalid: t('validation.invalid', {
                fieldName: t('payme.' + fieldName)
              })
            },
            styles: { base: { 'font-size': '14px' } }
          })

          const cardNumber = fields.create('cardNumber', fieldOptions('cardNumber'))
          const expiration = fields.create('cardExpiration', fieldOptions('cardExpiration'))
          const cvc = fields.create('cvc', fieldOptions('cvc'))

          Promise.all([
            cardNumber.mount('#card-number'),
            expiration.mount('#card-expiry'),
            cvc.mount('#card-cvv')
          ]).then(() => {
            cardNumber.on('focus', () => {
              focusHelper.value = 'number'
              number.value.focus()
            })
            cardNumber.on('change', () => {
              touchedHelper.number = true
            })
            cardNumber.on('validity-changed', (e) => {
              errorsHelper.number = (!e.isValid || e.isEmpty)
            })
            expiration.on('focus', () => {
              focusHelper.value = 'expiry'
              expiry.value.focus()
            })
            expiration.on('change', () => {
              touchedHelper.expiry = true
            })
            expiration.on('validity-changed', (e) => {
              errorsHelper.expiry = (!e.isValid || e.isEmpty)
            })
            cvc.on('focus', () => {
              focusHelper.value = 'cvv'
              cvv.value.focus()
            })
            cvc.on('change', () => {
              touchedHelper.cvv = true
            })
            cvc.on('validity-changed', (e) => {
              errorsHelper.cvv = (!e.isValid || e.isEmpty)
            })
            resolve({ success: true })
          })
        }).catch(error => {
          notifications.negative(error)
          resolve({ success: false })
          console.log('payme instantiation error', error)
        })
      })
    }

    const router = useRouter()
    onMounted(() => {
      if (!Object.keys(ventiStore.order).length) {
        router.push({ name: 'findOrder' })
      } else {
        const paymeHfInterval = setInterval(() => {
          if (typeof PayMe === 'function') {
            clearInterval(paymeHfInterval)
            initPayme().then(({ success }) => {
              initialized.value = !!success
            })
          }
        }, 250)

        if (!document.getElementById('payme-hf-script')) {
          const paymeHfScriptTag = document.createElement('script')
          paymeHfScriptTag.src = 'https://cdn.paymeservice.com/hf/v1/hostedfields.js'
          paymeHfScriptTag.id = 'payme-hf-script'
          document.getElementsByTagName('head')[0].appendChild(paymeHfScriptTag)
        }
      }
    })

    const { negative } = inject('Notifications')
    function paymeTokenize () {
      return new Promise(resolve => {
        const nameArr = state.name.toLowerCase().split(' ')
        paymeInstance.value.tokenize({
          payerFirstName: nameArr.shift(),
          payerLastName: nameArr.length ? nameArr.join(' ') : '',
          payerEmail: state.email,
          payerPhone: state.phone,
          payerSocialId: state.id,
          total: {
            label: label.value,
            amount: {
              currency: ventiStore.settings.currency.iso,
              value: '0.00'
            }
          }
        }).then(tokenizationResult => {
          resolve({
            success: true,
            result: tokenizationResult
          })
        }).catch(e => {
          if (e.validationError) {
            let fields = ''
            let count = 0
            for (const i in e.errors) {
              fields += t(`payme.fields.${i}`) + ', '
              count++
            }
            negative(t('payme.error', { count, fields }))
          } else {
            console.log('payme.tokenizationError', e.message)
            negative(e.message)
          }
          resolve({
            success: false,
            result: e
          })
        })
      })
    }

    const loading = ref(false)
    async function onSubmit () {
      focusHelper.value = ''
      v$.value.$touch()
      if (!v$.value.$invalid) {
        loading.value = true
        await paymeTokenize().then(({ success, result }) => {
          if (success) {
            emit('success', result)
            ventiStore.savePaymentInfo(result).then(({ success }) => {
              if (success) {
                router.push({ name: 'thankYou' })
              }
            })
          } else {
            if (result.payload && result.payload.status_error_code) {
              notifications.negative(t(`payme.errors.${result.payload.status_error_code}`))
            }
          }
        })
        loading.value = false
      }
    }

    return {
      formattedTotal,
      number,
      expiry,
      cvv,
      focusHelper,
      touchedHelper,
      errorsHelper,
      initialized,
      v$,
      state,
      loading,
      onSubmit
    }
  }
})
</script>
<style lang="sass" scoped>
.payme-target
  height: 24px
  transition: opacity 200ms ease-in-out
  opacity: 0.01

.q-field--float
  .payme-target
    opacity: 1
</style>
