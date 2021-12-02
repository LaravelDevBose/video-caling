<template>
    <li class="py-3 border-bottom">
        <h4 class="d-inline">{{ user.name }}</h4>
        <slot></slot>
    </li>
</template>

<script>
export default {
    name: "UserItem",
    props: ['user']
}
</script>

<style scoped>

</style>
