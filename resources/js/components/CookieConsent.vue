<script setup lang="ts">
import { ref, onMounted } from 'vue';

type Consent = {
	necessary: boolean;
	analytics: boolean;
	marketing: boolean;
};

const CONSENT_COOKIE = 'cookie_consent';
const DEFAULT_CONSENT: Consent = { necessary: true, analytics: false, marketing: false };

const showBanner = ref(false);
const showManager = ref(false);
const consent = ref<Consent>({ ...DEFAULT_CONSENT });

function readCookie(name: string): string | null {
	const value = `; ${document.cookie}`;
	const parts = value.split(`; ${name}=`);
	if (parts.length === 2) return parts.pop()!.split(';').shift() || null;
	return null;
}

function writeCookie(name: string, value: string, days = 180) {
	const date = new Date();
	date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
	document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
}

function saveConsent(newConsent: Consent) {
	consent.value = { ...newConsent, necessary: true };
	writeCookie(CONSENT_COOKIE, encodeURIComponent(JSON.stringify(consent.value)));
	showBanner.value = false;
	showManager.value = false;
	// Expose current consent for optional scripts to check
	(window as any).CookieConsent = {
		has: (key: keyof Consent) => !!(consent.value as any)[key],
		all: () => ({ ...consent.value }),
		openManager: () => (showManager.value = true),
	};
}

function acceptAll() {
	saveConsent({ necessary: true, analytics: true, marketing: true });
}

function rejectAll() {
	saveConsent({ necessary: true, analytics: false, marketing: false });
}

function saveChoices() {
	saveConsent(consent.value);
}

onMounted(() => {
	const raw = readCookie(CONSENT_COOKIE);
	if (raw) {
		try {
			const parsed = JSON.parse(decodeURIComponent(raw)) as Consent;
			consent.value = { necessary: true, analytics: !!parsed.analytics, marketing: !!parsed.marketing };
			showBanner.value = false;
		} catch {
			showBanner.value = true;
		}
	} else {
		showBanner.value = true;
	}
	// Provide global accessor even if banner is showing
	(window as any).CookieConsent = {
		has: (key: keyof Consent) => !!(consent.value as any)[key],
		all: () => ({ ...consent.value }),
		openManager: () => (showManager.value = true),
	};
});
</script>

<template>
	<!-- Banner -->
	<div
		v-if="showBanner"
		class="fixed inset-x-0 bottom-0 z-50 bg-white/95 dark:bg-gray-900/95 border-t border-gray-200 dark:border-gray-800 shadow-lg"
	>
		<div class="mx-auto max-w-5xl p-4 sm:flex sm:items-center sm:justify-between space-y-3 sm:space-y-0">
			<p class="text-sm text-gray-700 dark:text-gray-300">
				Usamos cookies necesarias para el funcionamiento del sitio. Puedes aceptar todas, rechazar las
				opcionales o gestionarlas. Las opcionales (analíticas/marketing) no se activarán sin tu
				consentimiento. Consulta nuestra
				<a :href="route('legal.cookies')" class="text-blue-600 hover:underline">Política de cookies</a>.
			</p>
			<div class="flex gap-2 shrink-0">
				<button
					class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-700 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-transparent dark:text-gray-200"
					@click="rejectAll">
					Rechazar opcionales
				</button>
				<button
					class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-700 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-transparent dark:text-gray-200"
					@click="showManager = true">
					Gestionar
				</button>
				<button class="px-3 py-2 text-sm rounded-md bg-green-600 text-white hover:bg-green-700"
					@click="acceptAll">
					Aceptar todas
				</button>
			</div>
		</div>
	</div>

	<!-- Manager Modal -->
	<div v-if="showManager" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
		<div class="w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6">
			<h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Preferencias de cookies</h2>
			<div class="space-y-3">
				<div class="flex items-start gap-3">
					<input type="checkbox" checked disabled class="mt-1">
					<div>
						<p class="font-medium text-gray-900 dark:text-gray-100">Necesarias</p>
						<p class="text-sm text-gray-600 dark:text-gray-300">
							Requeridas para proporcionar seguridad, sesión y funcionalidad básica.
						</p>
					</div>
				</div>
				<div class="flex items-start gap-3">
					<input id="consent-analytics" type="checkbox" v-model="consent.analytics" class="mt-1">
					<div>
						<label for="consent-analytics" class="font-medium text-gray-900 dark:text-gray-100">Analíticas</label>
						<p class="text-sm text-gray-600 dark:text-gray-300">
							Nos ayudan a entender el uso del sitio. Desactivadas por defecto.
						</p>
					</div>
				</div>
				<div class="flex items-start gap-3">
					<input id="consent-marketing" type="checkbox" v-model="consent.marketing" class="mt-1">
					<div>
						<label for="consent-marketing" class="font-medium text-gray-900 dark:text-gray-100">Marketing</label>
						<p class="text-sm text-gray-600 dark:text-gray-300">
							Usadas para personalización/publicidad. Desactivadas por defecto.
						</p>
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<button
					class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-700 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-transparent dark:text-gray-200"
					@click="showManager=false">
					Cancelar
				</button>
				<button
					class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-700 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-transparent dark:text-gray-200"
					@click="rejectAll">
					Rechazar opcionales
				</button>
				<button class="px-3 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700"
					@click="saveChoices">
					Guardar preferencias
				</button>
			</div>
		</div>
	</div>

	<!-- Floating manage button after consent -->
	<button
		v-if="!showBanner"
		class="fixed bottom-4 left-4 z-40 text-xs px-3 py-1.5 rounded-md bg-gray-900 text-white/90 hover:text-white"
		@click="showManager = true"
	>
		Cookies
	</button>
</template>


