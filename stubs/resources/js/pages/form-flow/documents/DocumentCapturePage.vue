<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import FormFlowPresentation from '@/pages/form-flow/core/components/FormFlowPresentation.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

type DocumentDefinition = { key: string; label: string; accept: string[]; required: boolean };
type UploadedDocument = { key: string; reference: string; name: string };
const props = defineProps<{
    documents: DocumentDefinition[];
    existing_documents?: { key: string; reference: string }[];
    preview_mode: boolean;
    upload_url_template: string | null;
    submit_url: string | null;
    presentation?: Record<string, unknown> | null;
}>();
const uploads = ref<Record<string, UploadedDocument>>(Object.fromEntries((props.existing_documents ?? []).map(item => [item.key, { ...item, name: 'File' }])));
const active = ref<string | null>(null);
const progress = ref(0);
const error = ref('');
const canContinue = computed(() => !active.value && props.documents.every(item => !item.required || uploads.value[item.key]));

function upload(item: DocumentDefinition, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || props.preview_mode || !props.upload_url_template) return;
    const url = props.upload_url_template.replace('DOCUMENT_KEY', encodeURIComponent(item.key));
    const body = new FormData();
    body.append('file', file);
    const xhr = new XMLHttpRequest();
    active.value = item.key;
    progress.value = 0;
    error.value = '';
    xhr.open('POST', url);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '');
    xhr.upload.onprogress = e => { if (e.lengthComputable) progress.value = Math.round(e.loaded / e.total * 100); };
    xhr.onload = () => {
        try {
            const result = JSON.parse(xhr.responseText);
            if (xhr.status < 200 || xhr.status >= 300 || typeof result.reference !== 'string') throw new Error();
            uploads.value = { ...uploads.value, [item.key]: { key: item.key, reference: result.reference, name: file.name } };
        } catch { error.value = 'Could not upload the document. Please try again.'; }
        active.value = null;
        input.value = '';
    };
    xhr.onerror = () => { error.value = 'Could not upload the document. Please try again.'; active.value = null; input.value = ''; };
    xhr.send(body);
}

function next(): void {
    if (props.preview_mode || !props.submit_url || !canContinue.value) return;
    router.post(props.submit_url, { data: { documents: Object.values(uploads.value).map(({ key, reference }) => ({ key, reference })) } });
}
</script>

<template>
    <FormFlowPresentation :presentation="presentation" screen="documents">
        <PublicLayout>
            <main class="mx-auto w-full max-w-lg space-y-6 px-5 py-8">
                <div><h1 class="text-2xl font-semibold">Documents</h1><p class="mt-2 text-muted-foreground">Add the documents requested by the issuer.</p></div>
                <section v-for="item in documents" :key="item.key" class="space-y-3 rounded-xl border p-4">
                    <div><h2 class="font-medium">{{ item.label }}</h2><p class="text-sm text-muted-foreground">{{ item.required ? 'Required' : 'Optional' }}</p></div>
                    <label class="inline-flex cursor-pointer rounded-lg border px-4 py-2 font-medium">
                        {{ uploads[item.key] ? 'Replace file' : 'Choose file' }}
                        <input class="sr-only" type="file" :accept="item.accept.join(',')" :disabled="preview_mode || !!active" :aria-label="`Upload ${item.label}`" @change="upload(item, $event)" />
                    </label>
                    <p v-if="active === item.key" role="status" class="text-sm">Uploading {{ progress }}%</p>
                    <p v-else-if="uploads[item.key]" role="status" class="text-sm">{{ uploads[item.key].name }} · Uploaded</p>
                </section>
                <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
                <button type="button" class="w-full rounded-lg bg-primary px-4 py-3 font-semibold text-primary-foreground disabled:opacity-50" :disabled="preview_mode || !canContinue" @click="next">Continue</button>
            </main>
        </PublicLayout>
    </FormFlowPresentation>
</template>
