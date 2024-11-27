<template lang="pug">
.row.relative-position
  loading-overlay(v-if="loading")
  .col-6.q-pa-sm(
    v-for="(opt, index) in options"
    :key="index"
  )
    .velo-select-card.relative-position.column.justify-between.items-stretch.q-px-sm.q-py-md(@click="$emit('selected', opt)")
      slot(
        name="cardHead"
        :opt="opt"
      )
      .row.flex.justify-between.items-center
        slot(
          name="topLeft"
          :opt="opt"
        )
        slot(
          name="topRight"
          :opt="opt"
        )
      .row
        slot(
          name="middle"
          :opt="opt"
        )
          .full-width
      .row.flex.justify-between.items-center
        slot(
          name="bottomLeft"
          :opt="opt"
        )
          div
        .flex.items-center
          slot(
            name="bottomRight"
            :opt="opt"
          )
          div(v-if="hideUnchecked && !isActive(opt)")
          .velo-select-card-check(v-else)
            .velo-select-card-checkmark(:class="{ active: isActive(opt) }")
</template>
<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'CardsSelect',

  props: {
    options: {
      type: Array,
      required: true
    },
    selected: {
      type: Object,
      required: false,
      default: () => ({})
    },
    indexCol: {
      type: String,
      required: false,
      default: 'id'
    },
    hideUnchecked: {
      type: Boolean,
      required: false,
      default: false
    },
    loading: {
      type: Boolean,
      required: false,
      default: false
    },
    shopify: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  emits: [
    'selected'
  ],

  setup (props) {
    function isActive (opt) {
      return Object.keys(props.selected).length && props.selected[props.indexCol] === opt[props.indexCol]
    }

    return {
      isActive
    }
  }
})
</script>
<style lang="sass" scoped>
.velo-select-card
  cursor: pointer
  border: solid 2px $primaryBlue
  border-radius: 12px
  min-height: 150px
.velo-select-card-check
  width: 23px
  height: 23px
  border-radius: 23px
  border: solid 2px $primaryBlue
  padding: 1px
.velo-select-card-checkmark
  width: 17px
  height: 17px
  border-radius: 17px
  background: white
  display: flex
  justify-content: center
  align-items: center
  transition: background-color 200ms ease-in-out
  &::before
    content: ''
    opacity: 0
    position: relative
    height: 7px
    width: 4px
    border: solid 0px $gray
    border-bottom-width: 1px
    border-right-width: 1px
    transform: rotateZ(45deg)
    transition: opacity 200ms ease-in-out
  &.active
    background-color: $primaryBlue
    &::before
      opacity: 1
</style>
