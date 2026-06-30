<script setup lang="ts">
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import NavBar from '@/components/NavBar.vue'

const route = useRoute()
const showNav = computed(() => route.name !== 'login')
</script>

<template>
  <NavBar v-if="showNav" />
  <main :class="showNav ? 'container-fluid' : ''">
    <RouterView />
  </main>
</template>
