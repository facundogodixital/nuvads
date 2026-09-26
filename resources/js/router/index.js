import { ref } from 'vue';
import { getLoginError } from '@/helpers/loginErrors';
import BrandPage from '@/pages/BrandPage/BrandPage.vue';
import LoginPage from '@/pages/LoginPage/LoginPage.vue';
import { useSessionStore } from '@/stores/sessionStore';
import CreatePage from '@/pages/CreatePage/CreatePage.vue';
import { createRouter, createWebHistory } from 'vue-router';
import LibraryPage from '@/pages/LibraryPage/LibraryPage.vue';
import NotFoundPage from '@/pages/NotFoundPage/NotFoundPage.vue';
import DashboardPage from '@/pages/DashboardPage/DashboardPage.vue';
import CompetitorPage from '@/pages/CompetitorPage/CompetitorPage.vue';
import InspirationPage from '@/pages/InspirationPage/InspirationPage.vue';
import CompetitorsPage from '@/pages/CompetitorsPage/CompetitorsPage.vue';
import LoginCallbackPage from '@/pages/LoginCallbackPage/LoginCallbackPage.vue';
import { applyTheme, getStoredTheme, getSystemTheme } from '@/helpers/preferencesStorage';
import { getAuthToken, clearAuthToken, rememberDestination, takeDestination } from '@/helpers/authStorage';

export const navigationError = ref('');

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: DashboardPage, meta: { requiresAuth: true } },
    { path: '/create', component: CreatePage, meta: { requiresAuth: true } },
    { path: '/library', component: LibraryPage, meta: { requiresAuth: true } },
    // Las pestañas de Mi marca comparten la página, que carga la marca una sola vez.
    { path: '/brand', component: BrandPage, props: { view: 'sources' }, meta: { requiresAuth: true } },
    { path: '/brand/profile', component: BrandPage, props: { view: 'profile' }, meta: { requiresAuth: true } },
    { path: '/brand/identity', component: BrandPage, props: { view: 'identity' }, meta: { requiresAuth: true } },
    {
      path: '/brand/sources/:sourceId',
      component: BrandPage,
      props: (route) => ({ view: 'source', sourceId: route.params.sourceId }),
      meta: { requiresAuth: true },
    },
    { path: '/competitors', component: CompetitorsPage, meta: { requiresAuth: true } },
    // La página del competidor lo carga una sola vez para su resumen y para el detalle de cada fuente.
    {
      path: '/competitors/:competitorId',
      component: CompetitorPage,
      props: (route) => ({ view: 'overview', competitorId: route.params.competitorId }),
      meta: { requiresAuth: true },
    },
    {
      path: '/competitors/:competitorId/sources/:sourceId',
      component: CompetitorPage,
      props: (route) => ({ view: 'source', competitorId: route.params.competitorId, sourceId: route.params.sourceId }),
      meta: { requiresAuth: true },
    },
    { path: '/inspiration', component: InspirationPage, meta: { requiresAuth: true } },
    {
      path: '/login',
      component: LoginPage,
      props: (route) => ({ error: getLoginError(route.query.error) }),
    },
    { path: '/login/callback', component: LoginCallbackPage },
    // También se conserva el destino de enlaces a futuras pantallas protegidas.
    { path: '/:pathMatch(.*)*', component: NotFoundPage, meta: { requiresAuth: true } },
  ],
});

router.beforeEach(async (to) => {
  const sessionStore = useSessionStore();
  navigationError.value = '';
  if (to.path === '/login/callback') {
    return true;
  }

  try {
    const token = getAuthToken();
    if (token) {
      await sessionStore.find();
      return to.path === '/login' ? takeDestination() : true;
    }
  } catch (error) {
    const accessWasRejected = error.code === 'unauthenticated' || error.code === 'account_disabled';
    if (!accessWasRejected) {
      navigationError.value = 'No se pudo comprobar el acceso. Vuelve a intentarlo.';
      return false;
    }
    clearAuthToken();
  }

  sessionStore.clear();

  if (to.meta.requiresAuth) {
    rememberDestination(to.fullPath);
    return '/login';
  }
  return true;
});

// El tema elegido por el usuario gana siempre; sin elección, el login arranca
// en dark por decisión de producto y el resto hereda la preferencia del sistema.
router.afterEach((to) => {
  const isLoginPage = to.path === '/login' || to.path === '/login/callback';
  const fallbackTheme = isLoginPage ? 'dark' : getSystemTheme();
  applyTheme(getStoredTheme() ?? fallbackTheme);
});

export default router;
