{{-- Usage: @include('partials.attachments', ['modelType' => 'Invoice', 'modelId' => $invoice->id]) --}}
@php
$existingAttachments = \App\Models\Attachment::where('attachable_type', $modelType)
    ->where('attachable_id', $modelId)
    ->where('business_id', Auth::user()->currentBusiness()->id)
    ->get();
@endphp
<div id="attachments-section" style="margin-top:16px;">
    <h4 style="margin:0 0 12px;">Attachments ({{ $existingAttachments->count() }})</h4>

    @foreach($existingAttachments as $att)
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:8px 12px;background:#f9f9f9;border-radius:4px;margin-bottom:6px;">
        <div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <a href="{{ route('attachments.download', $att) }}" style="font-weight:500;text-decoration:none;">{{ $att->original_name }}</a>
            <span style="color:#888;font-size:0.8rem;margin-left:8px;">{{ $att->size_human }}</span>
        </div>
        <button onclick="deleteAttachment({{ $att->id }}, this)" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:0.85rem;flex-shrink:0;">Delete</button>
    </div>
    @endforeach

    <div style="margin-top:8px;">
        <input type="file" id="attach-file" style="display:none;" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.csv,.doc,.docx">
        <button type="button" onclick="document.getElementById('attach-file').click()" class="btn btn-secondary" style="font-size:0.85rem;">+ Attach File</button>
        <span id="upload-status" style="margin-left:10px;font-size:0.85rem;color:#888;"></span>
    </div>
</div>

<script>
document.getElementById('attach-file').addEventListener('change', function () {
    const files = Array.from(this.files);
    const status = document.getElementById('upload-status');
    files.forEach(file => {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('attachable_type', '{{ $modelType }}');
        fd.append('attachable_id', '{{ $modelId }}');
        fd.append('_token', '{{ csrf_token() }}');
        status.textContent = 'Uploading ' + file.name + '...';
        fetch('{{ route("attachments.store") }}', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                status.textContent = 'Uploaded!';
                setTimeout(() => { status.textContent = ''; }, 2000);
                location.reload();
            })
            .catch(() => { status.textContent = 'Upload failed.'; });
    });
    this.value = '';
});

function deleteAttachment(id, btn) {
    if (!confirm('Delete this attachment?')) return;
    fetch('{{ route("attachments.destroy", ":id") }}'.replace(':id', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    }).then(() => btn.closest('div[style]').remove());
}
</script>
