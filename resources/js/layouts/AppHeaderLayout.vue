<script setup lang="ts">
import AppHeaderOnlyLayout from '@/layouts/app/AppHeaderOnlyLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppHeaderOnlyLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppHeaderOnlyLayout>
</template>
