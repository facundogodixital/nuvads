<template>
  <ul class="divide-y divide-border">
    <li
      v-for="review in reviews"
      :key="review.id"
      class="py-3"
    >
      <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
        <span class="font-medium text-text">{{ review.name ?? 'Cliente de Google' }}</span>
        <span
          role="img"
          :aria-label="`${review.stars} de 5 estrellas`"
        >{{ formatStars(review.stars) }}</span>
        <span aria-hidden="true">·</span>
        <time :datetime="review.date">{{ formatDate(review.date) }}</time>
      </p>
      <blockquote class="mt-1 max-w-prose text-sm leading-6 whitespace-pre-line">
        {{ review.text }}
      </blockquote>
    </li>
  </ul>
</template>


<script setup>
defineProps({
  // Las reseñas de una idea, en el orden en que la idea las muestra. name es el nombre de pila, o null si no lo tiene.
  reviews: { type: Array, required: true },
});

// Las estrellas de la reseña sobre cinco, llenas y vacías.
function formatStars(stars) {
  const filledStars = '★'.repeat(stars);
  const emptyStars = '☆'.repeat(5 - stars);
  return filledStars + emptyStars;
}

function formatDate(date) {
  // Con la hora, la fecha se lee en la zona del navegador y no se corre un día.
  const reviewDate = new Date(`${date}T00:00:00`);
  return reviewDate.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
