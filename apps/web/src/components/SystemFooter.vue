<template>
  <div class="system-footer text-center text-caption text-grey-7 q-py-sm q-px-md">
    Sistema desenvolvido por:
    <a
      href="https://lumencode.com.br"
      target="_blank"
      rel="noopener noreferrer"
      class="system-footer__link text-weight-medium"
    >LumenCode</a>
  </div>
</template>

<style scoped>
.system-footer {
  line-height: 1.4;
}

.system-footer__link {
  color: var(--q-primary);
  text-decoration: none;
}

.system-footer__link:hover,
.system-footer__link:focus-visible {
  text-decoration: underline;
}
</style>
