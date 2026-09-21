export const brandSections = [
  {
    id: 'business',
    title: 'Tu negocio',
    description: 'Lo que haces, lo que ofreces y por qué te eligen.',
    fields: [
      { key: 'name', label: 'Nombre de la marca', type: 'text', hint: 'El nombre de tu negocio' },
      { key: 'offer', label: 'Productos y servicios', hint: 'Qué vendes y qué servicios ofreces' },
      { key: 'difference', label: 'Qué te hace diferente', hint: 'Por qué te eligen frente a otras opciones' },
      { key: 'history', label: 'Tu historia', hint: 'Cómo empezaste y qué vale la pena contar' },
    ],
  },
  {
    id: 'audience',
    title: 'Tu público',
    description: 'A quién le hablamos y qué necesita resolver.',
    fields: [
      { key: 'customers', label: 'Quiénes te compran', hint: 'Describe tus clientes y dónde están' },
      { key: 'needs', label: 'Qué necesitan', hint: 'Qué buscan resolver cuando te contactan' },
    ],
  },
  {
    id: 'identity',
    title: 'Estilo y voz',
    description: 'Cómo se ve tu marca y cómo suena cuando habla.',
    fields: [
      { key: 'typography', label: 'Tipografías', hint: 'Las fuentes de tus títulos y textos' },
      { key: 'style', label: 'Estilo visual', hint: 'Fotos reales, fondos claros, diseños simples…' },
      { key: 'voice', label: 'Tu manera de hablar', hint: 'Cómo le hablas a tus clientes. Agrega alguna frase propia.' },
    ],
  },
  {
    id: 'customer-insights',
    title: 'Lo que dicen tus clientes',
    description: 'Sus palabras son una fuente de ideas para tu contenido.',
    fields: [
      { key: 'praise', label: 'Lo que más valoran', hint: 'Qué destacan en sus reseñas o conversaciones' },
      { key: 'questions', label: 'Preguntas y dudas frecuentes', hint: 'Las preguntas que respondes todos los días' },
    ],
  },
  {
    id: 'communication',
    title: 'Comunicación y oportunidades',
    description: 'Lo que ya estás contando y lo que podrías empezar a mostrar.',
    fields: [
      { key: 'topics', label: 'De qué hablas hoy', hint: 'Temas y formatos que ya publicas' },
      { key: 'opportunities', label: 'Oportunidades de contenido', hint: 'Qué te gustaría contar y todavía no estás mostrando' },
    ],
  },
];

export const researchSources = [
  {
    id: 'google-maps', field: 'google_maps_url', title: 'Google Maps', description: 'La voz de tus clientes',
    label: 'Enlace de tu negocio', inputType: 'url', placeholder: 'https://maps.google.com/…',
    dataLabel: 'Reseñas', resultLabel: 'Reseñas y reputación',
    emptyTitle: 'Tus reseñas, reunidas aquí',
    emptyDescription: 'Consulta los comentarios y las valoraciones que obtengamos de tu negocio.',
    insightDescription: 'Qué valoran tus clientes, críticas recurrentes y oportunidades, con las reseñas que las respaldan.',
    icon: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  },
  {
    id: 'instagram', field: 'instagram_username', title: 'Instagram', description: 'Tu comunicación en acción',
    label: 'Usuario o enlace del perfil', inputType: 'text', placeholder: '@tumarca',
    dataLabel: 'Publicaciones', resultLabel: 'Contenido y estilo',
    emptyTitle: 'Lo que ya estás publicando',
    emptyDescription: 'Revisa las publicaciones y sus textos, organizados para explorar tu comunicación.',
    insightDescription: 'Temas recurrentes, recursos visuales y tono de tus publicaciones, con su contenido de origen.',
    icon: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM17.5 6.5h.01',
  },
  {
    id: 'website', field: 'website_url', title: 'Sitio web', description: 'Tu negocio en tus palabras',
    label: 'Dirección de tu sitio', inputType: 'url', placeholder: 'https://tumarca.com',
    dataLabel: 'Páginas', resultLabel: 'Oferta e historia',
    emptyTitle: 'El contenido de tu sitio',
    emptyDescription: 'Explora las páginas recopiladas y los textos sobre tus productos, servicios e historia.',
    insightDescription: 'Tu oferta, diferenciales y público a partir de lo que cuenta tu sitio, con sus páginas de origen.',
    icon: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c4 5 4 13 0 18-4-5-4-13 0-18Z',
  },
];
