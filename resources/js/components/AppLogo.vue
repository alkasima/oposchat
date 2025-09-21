<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="route('home')" class="flex items-center hover:opacity-90 transition-opacity duration-300">
        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-orange-500 text-white">
            <AppLogoIcon class="size-5" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">OposChat</span>
        </div>
    </Link>
</template>
