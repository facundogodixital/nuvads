<template>
  <main>
    <h1>Nuvads</h1>
    <p
      v-if="error"
      role="alert"
    >
      {{ error }}
    </p>

    <section v-if="user">
      <h2>Hola, {{ user.name }}</h2>
      <p>{{ user.email }}</p>
      <p>Identificador de tu cuenta: <strong>{{ user.login_identifier }}</strong></p>

      <form
        method="post"
        action="/auth/logout"
      >
        <input
          type="hidden"
          name="_token"
          :value="csrfToken"
        >
        <button type="submit">
          Cerrar sesión
        </button>
      </form>
    </section>

    <section v-else>
      <p>Ingresá o creá tu cuenta con Google.</p>
      <a href="/auth/google/redirect">Continuar con Google</a>
    </section>
  </main>
</template>


<script setup>
defineProps({
  user: { type: Object, default: null },
  error: { type: String, default: '' },
  csrfToken: { type: String, required: true },
});
</script>
