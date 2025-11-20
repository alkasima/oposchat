<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, TrendingUp, Loader2 } from 'lucide-vue-next';

interface PlanChangeConfirmationProps {
    show: boolean;
    currentPlan: {
        key: string;
        name: string;
        price: number;
    };
    targetPlan: {
        key: string;
        name: string;
        price: number;
    };
    priceDifference: number;
    currency: string;
    isUpgrade: boolean;
    isDowngrade?: boolean;
    loading?: boolean;
    errorMessage?: string | null;
}

const props = defineProps<PlanChangeConfirmationProps>();

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const safePriceDifference = computed(() => {
    const value = Number(props.priceDifference);
    return Number.isFinite(value) ? Math.max(value, 0) : 0;
});

const formattedPrice = computed(() => {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: props.currency.toUpperCase()
    }).format(safePriceDifference.value);
});

const shouldShowPriceNotice = computed(() => props.isUpgrade && safePriceDifference.value > 0);
const shouldShowDowngradeNotice = computed(() => props.isDowngrade);

const handleConfirm = () => {
    emit('confirm');
};

const handleCancel = () => {
    emit('cancel');
};
</script>

<template>
    <Dialog :open="show" @update:open="(open) => !open && handleCancel()">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <TrendingUp class="w-5 h-5 text-orange-500" />
                    Confirmar cambio de plan
                </DialogTitle>
                <DialogDescription>
                    Estás a punto de cambiar tu plan de suscripción
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Current Plan -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Plan actual</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ currentPlan.name }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency.toUpperCase() }).format(currentPlan.price) }}/mes
                    </p>
                </div>

                <!-- Arrow -->
                <div class="flex justify-center">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                </div>

                <!-- New Plan -->
                <div class="bg-orange-50 dark:bg-orange-900/20 border-2 border-orange-200 dark:border-orange-800 rounded-lg p-4">
                    <p class="text-sm text-orange-600 dark:text-orange-400 mb-1">Nuevo plan</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ targetPlan.name }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency.toUpperCase() }).format(targetPlan.price) }}/mes
                    </p>
                </div>

                <!-- Price Difference -->
                <div v-if="shouldShowPriceNotice" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="w-5 h-5 text-blue-500 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                Cargo adicional
                            </p>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                Se te cobrará {{ formattedPrice }} por la diferencia de precio.
                                El cambio será efectivo inmediatamente.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Downgrade Notice -->
                <div v-if="shouldShowDowngradeNotice" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="w-5 h-5 text-amber-500 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-amber-900 dark:text-amber-100">
                                Cambio programado al final del período
                            </p>
                            <p class="text-sm text-amber-800 dark:text-amber-200 mt-1">
                                Mantendrás tu plan actual hasta el final del ciclo de facturación. Después, cambiaremos automáticamente al plan seleccionado.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="props.errorMessage" class="text-sm text-red-600 dark:text-red-400 mt-2">
                {{ props.errorMessage }}
            </p>

            <DialogFooter>
                <Button 
                    variant="outline" 
                    @click="handleCancel"
                    :disabled="loading"
                >
                    Cancelar
                </Button>
                <Button 
                    @click="handleConfirm"
                    :disabled="loading"
                    class="bg-orange-500 hover:bg-orange-600 text-white"
                >
                    <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
                    Confirmar cambio
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
