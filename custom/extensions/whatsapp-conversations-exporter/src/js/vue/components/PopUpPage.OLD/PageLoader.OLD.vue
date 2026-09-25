<template>
  <div 
    id="page-loader"
    :class="{'show': showPageLoader}"
  />
</template>


<script>
import { mapGetters } from 'vuex'


export default {
  name: 'PageLoader',
  computed: {
    ...mapGetters({
      showPageLoader: 'popup/showPageLoader',
    })
  }
}
</script>
