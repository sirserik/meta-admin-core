<script setup>
import { ref, computed } from 'vue';

/**
 * Click-on-image focal point picker.
 *
 * v-model receives / emits `{ x: Number, y: Number }` with both in the
 * [0..1] range (origin top-left). Drop a pre-existing image URL via
 * `:src` and the user clicks where the subject of the image lives —
 * the crosshair snaps to that coordinate.
 *
 *   <FocalPointPicker :src="img.url" v-model="form.focal" />
 *
 * The helper ImageService::focalCrop() on the server reads the same
 * {x,y} pair and produces a cropped variant with that point
 * guaranteed visible in every aspect ratio.
 */
const props = defineProps({
    src:       { type: String,  required: true },
    modelValue:{ type: Object,  default: () => ({ x: 0.5, y: 0.5 }) },
    disabled:  { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const imgRef = ref(null);

const fx = computed(() => Math.max(0, Math.min(1, Number(props.modelValue?.x ?? 0.5))));
const fy = computed(() => Math.max(0, Math.min(1, Number(props.modelValue?.y ?? 0.5))));

function pickAt(event) {
    if (props.disabled || !imgRef.value) return;
    const rect = imgRef.value.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top)  / rect.height;
    emit('update:modelValue', {
        x: +Math.max(0, Math.min(1, x)).toFixed(3),
        y: +Math.max(0, Math.min(1, y)).toFixed(3),
    });
}

function reset() {
    emit('update:modelValue', { x: 0.5, y: 0.5 });
}
</script>

<template>
    <div class="space-y-2">
        <div class="relative inline-block max-w-full rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600"
             :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-crosshair'">
            <img ref="imgRef" :src="src" @click="pickAt"
                 class="block max-w-full max-h-96 select-none" draggable="false">

            <!-- Crosshair -->
            <div v-if="src" class="pointer-events-none absolute inset-0">
                <div class="absolute w-6 h-6 -ml-3 -mt-3 rounded-full border-2 border-white shadow-[0_0_0_2px_rgba(0,0,0,0.55)]"
                     :style="{ left: (fx * 100) + '%', top: (fy * 100) + '%' }">
                    <div class="absolute inset-0 rounded-full bg-red-500/70"></div>
                </div>
                <!-- Thin guide lines -->
                <div class="absolute top-0 bottom-0 w-px bg-white/60 mix-blend-difference"
                     :style="{ left: (fx * 100) + '%' }"></div>
                <div class="absolute left-0 right-0 h-px bg-white/60 mix-blend-difference"
                     :style="{ top: (fy * 100) + '%' }"></div>
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
            <span>Фокус: <span class="font-mono">{{ fx.toFixed(2) }} × {{ fy.toFixed(2) }}</span></span>
            <button type="button" @click="reset" :disabled="disabled"
                    class="underline hover:text-gray-700 dark:hover:text-gray-200 disabled:no-underline">
                сбросить в центр
            </button>
            <span class="ml-auto">Клик по изображению — выбрать точку</span>
        </div>
    </div>
</template>
