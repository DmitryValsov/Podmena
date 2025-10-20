<script setup lang="ts">

import { Head, usePage } from '@inertiajs/vue3';

//defineProps<Props>();

const page = usePage();
const user = page.props.auth.user;

</script>

<template>

        <Head title="Welcome" />
        <h1>Welcome</h1>

        <p>Hello {{ user.name }}, welcome to your first Inertia app!</p>

</template>
