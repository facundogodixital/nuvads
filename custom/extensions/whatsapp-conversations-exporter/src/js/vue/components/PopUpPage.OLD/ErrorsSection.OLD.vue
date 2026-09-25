<template>
  <div class="errors-section mt-2" v-if="hasToBeShown">
    <b-alert show variant="danger" v-for="(errors, i) in errors" :key="`popup-errors-${i}`">
      {{ errors.message }}
    </b-alert>
  </div>
</template>


<script>
  import { mapGetters } from 'vuex';


  export default {
    name: 'ErrorsSection',
    computed: {
      ...mapGetters({
        errors: 'popup/errors',
      }),
      hasToBeShown() {
        return this.errors && this.errors.length;
      },
    },
    async mounted() {
    },
  }
</script>


<style scoped>
  .message-text-legend {
    font-size: 0.9em;
    color: #207EBC;
  }
  .message-text-icon {
    color: #207EBC;
    font-size: 1.1em;
  }
</style>

