<template lang="pug">
q-form.text-center.relative-position(@submit="onSubmit")
  loading-overlay(v-if="loading")
  q-input.required(
    filled
    v-model="state.orderName"
    :disable="loading"
    :label="$t('firstForm.orderName')"
    :error="!!v$?.orderName?.$errors.length"
    :error-message="!!v$?.orderName?.$errors.length ? $t('validation.' + v$.orderName.$errors[0].$validator, v$.orderName.$errors[0].$params) : ''"
  )
  q-input.required(
    filled
    type="number"
    v-model="state.phone"
    :disable="loading"
    :label="$t('firstForm.phone')"
    :error="!!v$?.phone?.$errors.length"
    :error-message="!!v$?.phone?.$errors.length ? $t('validation.' + v$.phone.$errors[0].$validator, v$.phone.$errors[0].$params) : ''"
    @update:model-value="state.phone = state.phone.replace(/[^0-9]/g, '')"
  )
  q-btn.full-width.velo-form-btn(
    unelevated
    no-caps
    type="submit"
    color="primaryBlue"
    :label="$t('firstForm.cta')"
  )
  .text-smaller.text-darkGray.q-mt-md(v-html="$t('firstForm.disclaimer', { url: returnsPolicyUrl })")
</template>

<script>
import { defineComponent, reactive, computed, ref } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, numeric, alphaNum } from '@vuelidate/validators'
import { useVentiStore } from 'src/stores/venti'
import { useRouter } from 'vue-router'

export default defineComponent({
  name: 'PageOrderSelect',

  setup () {
    const state = reactive({
      orderName: '',
      phone: ''
    })

    const rules = computed(() => {
      return {
        orderName: { required, alphaNum },
        phone: { required, numeric }
      }
    })

    const v$ = useVuelidate(rules, state)

    const ventiStore = useVentiStore()
    const loading = ref(false)
    const router = useRouter()
    async function onSubmit () {
      v$.value.$touch()
      if (!v$.value.$invalid) {
        loading.value = true
        await ventiStore.searchOrder(state).then(({ success, result }) => {
          if (success) {
            router.push({ name: 'deliverySelect' })
          } else {
            loading.value = false
          }
        })
      }
    }

    const returnsPolicyUrl = computed(() => ventiStore.settings.returnsPolicyUrl)

    return {
      v$,
      state,
      loading,
      onSubmit,
      returnsPolicyUrl
    }
  }
})
</script>
