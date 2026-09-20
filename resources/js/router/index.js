import { ref } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '@/pages/LoginPage/LoginPage.vue';
import DashboardPage from '@/pages/DashboardPage/DashboardPage.vue';
import NotFoundPage from '@/pages/NotFoundPage/NotFoundPage.vue';
import LoginCallbackPage from '@/pages/LoginCallbackPage/LoginCallbackPage.vue';
import { useSessionStore } from '@/stores/sessionStore';
import { getLoginError } from '@/helpers/loginErrors';
import { applyTheme, getStoredTheme, getSystemTheme } from '@/helpers/preferencesStorage';
import { getAuthToken, clearAuthToken, rememberDestination, takeDestination } from '@/helpers/authStorage';

export const navigationError = ref('');

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: DashboardPage, meta: { requiresAuth: true } },
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
