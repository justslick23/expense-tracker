
@extends('layouts.shell')

@section('content')
<div class="container py-4">

    {{-- Page Header --}}
    <div class="mb-4">
        <h3 class="fw-bold mb-1">Scan Receipt</h3>
        <p class="text-body-secondary mb-0">
            Take a photo or upload an existing receipt.
            Your receipt will be saved securely and prepared
            for AI scanning.
        </p>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <form
                        id="receiptUploadForm"
                        action="{{ route('receipts.store') }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        @csrf

                        {{-- Capture Options --}}
                        <h5 class="fw-semibold mb-3">
                            Add Your Receipt
                        </h5>

                        <div class="row g-3 mb-4">

                            {{-- Take Photo --}}
                            <div class="col-6">
                                <button
                                    type="button"
                                    id="takePhotoBtn"
                                    class="btn btn-outline-primary w-100 py-4 h-100"
                                >
                                    <i class="fas fa-camera fa-2x d-block mb-3"></i>

                                    <span class="fw-semibold d-block">
                                        Take Photo
                                    </span>

                                    <small class="d-block mt-1 opacity-75">
                                        Use your camera
                                    </small>
                                </button>
                            </div>

                            {{-- Upload Existing Image --}}
                            <div class="col-6">
                                <button
                                    type="button"
                                    id="uploadReceiptBtn"
                                    class="btn btn-outline-secondary w-100 py-4 h-100"
                                >
                                    <i class="fas fa-image fa-2x d-block mb-3"></i>

                                    <span class="fw-semibold d-block">
                                        Upload Receipt
                                    </span>

                                    <small class="d-block mt-1 opacity-75">
                                        Choose an image
                                    </small>
                                </button>
                            </div>

                        </div>

                        {{-- Hidden Camera Input --}}
                        <input
                            type="file"
                            id="cameraInput"
                            accept="image/jpeg,image/png,image/webp"
                            capture="environment"
                            class="d-none"
                            aria-label="Take a receipt photo"
                        >

                        {{-- Hidden Gallery Input --}}
                        <input
                            type="file"
                            id="galleryInput"
                            accept="image/jpeg,image/png,image/webp"
                            class="d-none"
                            aria-label="Choose a receipt image"
                        >

                        {{-- Actual Form Input --}}
                        <input
                            type="file"
                            id="receiptInput"
                            name="receipt"
                            accept="image/jpeg,image/png,image/webp"
                            class="visually-hidden"
                            tabindex="-1"
                            aria-label="Selected receipt"
                            required
                        >

                        {{-- Preview --}}
                        <div
                            id="receiptPreviewContainer"
                            class="d-none mb-4"
                        >
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-semibold mb-0">
                                    Receipt Preview
                                </h6>

                                <button
                                    type="button"
                                    id="removeReceiptBtn"
                                    class="btn btn-sm btn-outline-danger"
                                >
                                    <i class="fas fa-trash-alt me-1"></i>
                                    Remove
                                </button>
                            </div>

                            <div
                                class="border rounded-3 p-3 text-center"
                                style="background: var(--bs-tertiary-bg);"
                            >
                                <img
                                    id="receiptPreview"
                                    alt="Selected receipt preview"
                                    class="img-fluid rounded"
                                    style="
                                        max-height: 450px;
                                        width: 100%;
                                        object-fit: contain;
                                    "
                                >
                            </div>

                            <div class="mt-3">
                                <div
                                    id="selectedFileName"
                                    class="small fw-semibold text-break"
                                ></div>

                                <div
                                    id="selectedFileSize"
                                    class="small text-body-secondary mt-1"
                                ></div>
                            </div>
                        </div>

                        {{-- Information --}}
                        <div class="alert alert-info border-0 mb-4">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-info-circle mt-1"></i>

                                <div class="small">
                                    Ensure the entire receipt is visible
                                    and the text is clear.

                                    Supported formats:
                                    JPG, PNG and WebP.

                                    Maximum size: 10 MB.
                                </div>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <button
                            type="submit"
                            id="submitReceiptBtn"
                            class="btn btn-primary w-100 py-3 fw-semibold"
                            disabled
                        >
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Upload Receipt
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-body-secondary">
                                Your receipt is stored privately.
                                You'll review the extracted details
                                before saving an expense.
                            </small>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('receiptUploadForm');

    const cameraInput = document.getElementById('cameraInput');
    const galleryInput = document.getElementById('galleryInput');
    const receiptInput = document.getElementById('receiptInput');

    const takePhotoBtn = document.getElementById('takePhotoBtn');
    const uploadReceiptBtn = document.getElementById('uploadReceiptBtn');
    const removeReceiptBtn = document.getElementById('removeReceiptBtn');
    const submitReceiptBtn = document.getElementById('submitReceiptBtn');

    const previewContainer = document.getElementById(
        'receiptPreviewContainer'
    );

    const previewImage = document.getElementById('receiptPreview');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileSize = document.getElementById('selectedFileSize');

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    const maxFileSize = 10 * 1024 * 1024;

    let previewUrl = null;

    // Open mobile camera when supported.
    takePhotoBtn.addEventListener('click', function () {
        cameraInput.click();
    });

    // Open existing image picker.
    uploadReceiptBtn.addEventListener('click', function () {
        galleryInput.click();
    });

    // Handle both input sources.
    cameraInput.addEventListener('change', function () {
        handleSelectedFile(this.files[0]);
    });

    galleryInput.addEventListener('change', function () {
        handleSelectedFile(this.files[0]);
    });

    function handleSelectedFile(file) {

        if (!file) {
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            alert('Please select a JPG, PNG or WebP image.');
            return;
        }

        if (file.size > maxFileSize) {
            alert('The receipt image must not exceed 10 MB.');
            return;
        }

        // Transfer the selected image to the actual form input.
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        receiptInput.files = dataTransfer.files;

        // Remove the previous preview URL to prevent memory leaks.
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        previewUrl = URL.createObjectURL(file);
        previewImage.src = previewUrl;

        selectedFileName.textContent = file.name;

        selectedFileSize.textContent =
            (file.size / 1024 / 1024).toFixed(2) + ' MB';

        previewContainer.classList.remove('d-none');

        submitReceiptBtn.disabled = false;

        submitReceiptBtn.innerHTML =
            '<i class="fas fa-cloud-upload-alt me-2"></i> Upload Receipt';
    }

    // Remove selected image.
    removeReceiptBtn.addEventListener('click', function () {

        cameraInput.value = '';
        galleryInput.value = '';
        receiptInput.value = '';

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        previewImage.removeAttribute('src');

        selectedFileName.textContent = '';
        selectedFileSize.textContent = '';

        previewContainer.classList.add('d-none');

        submitReceiptBtn.disabled = true;
    });

    // Prevent multiple submissions.
    form.addEventListener('submit', function (event) {

        if (!receiptInput.files.length) {
            event.preventDefault();
            alert('Please take a photo or select a receipt first.');
            return;
        }

        submitReceiptBtn.disabled = true;

        submitReceiptBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Uploading Receipt...';
    });

    window.addEventListener('pagehide', function () {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    });

});
</script>
@endsection
