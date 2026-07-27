<template>
  <svg
    v-if="path"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    v-bind="$attrs"
  >
    <path v-for="(d, i) in paths" :key="i" :d="d" />
    <template v-if="extra">
      <component v-for="(el, i) in extra" :key="i" :is="el.tag" v-bind="el.attrs" />
    </template>
  </svg>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ name: String })

const icons = {
  'grid':           { paths: ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M3 14h7v7H3z', 'M14 14h7v7h-7z'] },
  'users':          { paths: ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'], extra: [{ tag: 'circle', attrs: { cx: 9, cy: 7, r: 4 } }, { tag: 'path', attrs: { d: 'M23 21v-2a4 4 0 0 0-3-3.87' } }, { tag: 'path', attrs: { d: 'M16 3.13a4 4 0 0 1 0 7.75' } }] },
  'alert-triangle': { paths: ['M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z', 'M12 9v4', 'M12 17h.01'] },
  'alert-circle':   { paths: ['M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z', 'M12 8v4', 'M12 16h.01'] },
  'leaf':           { paths: ['M17 8C8 10 5.9 16.17 3.82 22', 'M21 3a22.33 22.33 0 0 0-14.36 9'] },
  'clipboard':      { paths: ['M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'], extra: [{ tag: 'rect', attrs: { x: 8, y: 2, width: 8, height: 4, rx: 1, ry: 1 } }] },
  'shield-off':     { paths: ['M19.69 14a6.9 6.9 0 0 0 .31-2V5l-8-3-3.16 1.18', 'M4.73 4.73L4 5v7c0 6 8 10 8 10a20.29 20.29 0 0 0 5.62-4.38', 'M1 1l22 22'] },
  'user-check':     { paths: ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M16 11l2 2 4-4'], extra: [{ tag: 'circle', attrs: { cx: 8.5, cy: 7, r: 4 } }] },
  'briefcase':      { paths: ['M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z', 'M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16'] },
  'tool':           { paths: ['M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'] },
  'menu':           { paths: ['M3 12h18', 'M3 6h18', 'M3 18h18'] },
  'sun':            { paths: ['M12 1v2', 'M12 21v2', 'M4.22 4.22l1.42 1.42', 'M18.36 18.36l1.42 1.42', 'M1 12h2', 'M21 12h2', 'M4.22 19.78l1.42-1.42', 'M18.36 5.64l1.42-1.42'], extra: [{ tag: 'circle', attrs: { cx: 12, cy: 12, r: 5 } }] },
  'moon':           { paths: ['M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z'] },
  'log-out':        { paths: ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'M16 17l5-5-5-5', 'M21 12H9'] },
  'x':              { paths: ['M18 6L6 18', 'M6 6l12 12'] },
  'check':          { paths: ['M20 6L9 17l-5-5'] },
  'chevron-right':  { paths: ['M9 18l6-6-6-6'] },
  'plus':           { paths: ['M12 5v14', 'M5 12h14'] },
  'search':         { paths: ['M21 21l-4.35-4.35'], extra: [{ tag: 'circle', attrs: { cx: 11, cy: 11, r: 8 } }] },
  'eye':            { paths: ['M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'], extra: [{ tag: 'circle', attrs: { cx: 12, cy: 12, r: 3 } }] },
  'edit':           { paths: ['M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7', 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z'] },
  'trash-2':        { paths: ['M3 6h18', 'M19 6l-1 14H6L5 6', 'M8 6V4h8v2'], extra: [{ tag: 'line', attrs: { x1: 10, y1: 11, x2: 10, y2: 17 } }, { tag: 'line', attrs: { x1: 14, y1: 11, x2: 14, y2: 17 } }] },
  'filter':         { paths: ['M22 3H2l8 9.46V19l4 2v-8.54L22 3z'] },
  'download':       { paths: ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3'] },
}

const current = computed(() => icons[props.name] || icons['grid'])
const path    = computed(() => !!current.value)
const paths   = computed(() => current.value?.paths || [])
const extra   = computed(() => current.value?.extra || null)
</script>
