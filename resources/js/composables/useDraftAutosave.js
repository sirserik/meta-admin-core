import { onMounted, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Debounced localStorage-backed draft autosave for any Inertia form.
 *
 *   const { restorePrompt, savedAt, discard, acceptRestore, declineRestore } =
 *       useDraftAutosave(form, {
 *           key:   `block:${props.item.id ?? 'new'}`,
 *           debounce: 1500,
 *       });
 *
 * Writes `form.data()` into localStorage under `admin-core:draft:{key}` on
 * every change (debounced). When the same form mounts again and the stored
 * blob is newer than the record's `updated_at` (optional second arg) — or
 * simply exists for a brand-new record — the composable prompts the editor
 * to restore it. Accepting overlays the stored payload onto the form.
 *
 * The draft is wiped on successful submit (`form.wasSuccessful`) and on
 * manual discard.
 *
 * v1 is purely client-side: no backend table, no schema, no tied-to-user
 * identity. Per-editor per-machine, which matches 95% of real recovery
 * scenarios (browser crash, accidental tab close, network flake).
 */
export function useDraftAutosave(form, opts = {}) {
    const {
        key,
        debounce = 1500,
        reference_updated_at = null,
    } = opts;

    const storageKey = `admin-core:draft:${key}`;
    const savedAt      = ref(null);
    const restorePrompt = ref(null);   // { savedAt, data } or null

    let timeout = null;
    let skipNext = true;  // don't save immediately after a restore

    function load() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.data || !parsed.savedAt) return null;
            return parsed;
        } catch { return null; }
    }

    function save() {
        try {
            const payload = { data: form.data(), savedAt: new Date().toISOString() };
            localStorage.setItem(storageKey, JSON.stringify(payload));
            savedAt.value = payload.savedAt;
        } catch { /* quota exceeded, silently drop */ }
    }

    function discard() {
        try { localStorage.removeItem(storageKey); } catch {}
        savedAt.value   = null;
        restorePrompt.value = null;
    }

    function acceptRestore() {
        if (!restorePrompt.value) return;
        for (const [k, v] of Object.entries(restorePrompt.value.data)) {
            // Don't overwrite keys the form doesn't know about — avoids
            // polluting useForm() with stale fields after schema changes.
            if (k in form) form[k] = v;
        }
        skipNext = true;
        restorePrompt.value = null;
    }

    function declineRestore() {
        discard();
    }

    onMounted(() => {
        const stored = load();
        if (!stored) return;
        // If we have a reference timestamp (record.updated_at), only
        // suggest restore when the draft is newer — otherwise the draft
        // is stale from a previous edit session that already landed.
        // If the reference isn't a parseable ISO date (e.g. admin renders
        // it as "19.04.2026 12:34"), err on the side of showing the
        // prompt rather than silently dropping the draft.
        let ref = 0;
        if (reference_updated_at) {
            const t = new Date(reference_updated_at).getTime();
            if (!Number.isNaN(t)) ref = t;
        }
        const drf = new Date(stored.savedAt).getTime();
        if (Number.isNaN(drf) || drf > ref) {
            savedAt.value        = stored.savedAt;
            restorePrompt.value  = stored;
        } else {
            discard();
        }
    });

    // Debounced save on any form change.
    watch(
        () => JSON.stringify(form.data()),
        () => {
            if (skipNext) { skipNext = false; return; }
            clearTimeout(timeout);
            timeout = setTimeout(save, debounce);
        },
        { deep: true },
    );

    // Wipe draft on successful submit.
    watch(() => form.wasSuccessful, (ok) => { if (ok) discard(); });

    onBeforeUnmount(() => clearTimeout(timeout));

    return { savedAt, restorePrompt, discard, acceptRestore, declineRestore };
}
