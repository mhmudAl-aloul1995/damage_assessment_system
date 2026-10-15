<script>
    $(function () {
        const images = @json($imageAttachments);
        const viewer = document.getElementById('cso_attachment_viewer');
        const stage = document.getElementById('cso_viewer_stage');
        const viewerImage = document.getElementById('cso_viewer_image');
        const previewImage = document.getElementById('cso_gallery_image');
        let selectedIndex = 0;
        let zoom = 1;
        let viewerOpener = null;

        function fitViewerImage() {
            const unavailable = !viewerImage || viewerImage.hidden;
            $('#cso_zoom_reset').text(Math.round(zoom * 100) + '%');
            $('#cso_zoom_out').prop('disabled', unavailable || zoom <= 1);
            $('#cso_zoom_in').prop('disabled', unavailable || zoom >= 4);
            if (!viewerImage || !stage || !viewerImage.naturalWidth || viewerImage.hidden) { return; }
            const fit = Math.min(1, Math.max(1, stage.clientWidth - 40) / viewerImage.naturalWidth, Math.max(1, stage.clientHeight - 40) / viewerImage.naturalHeight);
            viewerImage.style.width = Math.round(viewerImage.naturalWidth * fit * zoom) + 'px';
            viewerImage.style.height = Math.round(viewerImage.naturalHeight * fit * zoom) + 'px';
        }

        function setViewerImage(image) {
            zoom = 1;
            viewerImage.hidden = false;
            $('#cso_viewer_error').prop('hidden', true);
            $('#cso_viewer_name').text(image.name);
            $('#cso_viewer_original').attr('href', image.url);
            viewerImage.alt = image.name;
            viewerImage.src = image.url;
            stage.scrollTop = 0;
            stage.scrollLeft = 0;
            fitViewerImage();
        }

        function selectImage(index) {
            if (index < 0 || index >= images.length) { return; }
            selectedIndex = index;
            const image = images[index];
            if (previewImage) {
                previewImage.hidden = false;
                $('#cso_gallery_error').prop('hidden', true);
                previewImage.alt = image.name;
                previewImage.src = image.url;
            }
            $('#cso_gallery_name').text(image.name).attr('title', image.name);
            $('[data-cso-image-counter]').text((index + 1) + ' من ' + images.length);
            $('[data-cso-image-index]').each(function () {
                const active = Number(this.dataset.csoImageIndex) === index;
                $(this).toggleClass('active', active).attr('aria-pressed', String(active));
                if (active) {
                    const scroller = this.closest('.cso-gallery-scroll, .cso-viewer-thumbnails');
                    if (!scroller || !scroller.getClientRects().length) { return; }
                    const bounds = this.getBoundingClientRect();
                    const scrollBounds = scroller.getBoundingClientRect();
                    if (bounds.top < scrollBounds.top) { scroller.scrollTop -= scrollBounds.top - bounds.top; }
                    if (bounds.bottom > scrollBounds.bottom) { scroller.scrollTop += bounds.bottom - scrollBounds.bottom; }
                }
            });
            $('[data-cso-image-step="-1"]').prop('disabled', index === 0);
            $('[data-cso-image-step="1"]').prop('disabled', index === images.length - 1);
            if (viewer.classList.contains('show')) { setViewerImage(image); }
        }

        if (previewImage) {
            previewImage.addEventListener('error', function () {
                previewImage.hidden = true;
                $('#cso_gallery_error').prop('hidden', false);
            });
            if (previewImage.complete && !previewImage.naturalWidth) { previewImage.dispatchEvent(new Event('error')); }
        }
        if (viewerImage) {
            viewerImage.addEventListener('load', fitViewerImage);
            viewerImage.addEventListener('error', function () {
                viewerImage.hidden = true;
                $('#cso_viewer_error').prop('hidden', false);
                fitViewerImage();
            });
        }
        $('[data-cso-image-index] img').on('error', function () {
            this.hidden = true;
            this.parentElement.classList.add('cso-image-unavailable');
        }).each(function () {
            if (this.complete && !this.naturalWidth) { this.dispatchEvent(new Event('error')); }
        });

        $('[data-cso-image-index]').on('click', function () { selectImage(Number(this.dataset.csoImageIndex)); });
        $('[data-cso-image-step]').on('click', function () { selectImage(selectedIndex + Number(this.dataset.csoImageStep)); });
        $('[data-cso-open-gallery]').on('click', function () {
            viewerOpener = this;
            bootstrap.Modal.getOrCreateInstance(viewer).show();
        });
        viewer.addEventListener('show.bs.modal', function () {
            if (images.length) { setViewerImage(images[selectedIndex]); }
        });
        viewer.addEventListener('shown.bs.modal', fitViewerImage);
        viewer.addEventListener('hidden.bs.modal', function () { viewerOpener?.focus({ preventScroll: true }); });
        viewer.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                selectImage(selectedIndex + (event.key === 'ArrowLeft' ? 1 : -1));
            }
        });
        $('#cso_zoom_in').on('click', function () { zoom = Math.min(4, zoom + .5); fitViewerImage(); });
        $('#cso_zoom_out').on('click', function () { zoom = Math.max(1, zoom - .5); fitViewerImage(); });
        $('#cso_zoom_reset').on('click', function () { zoom = 1; fitViewerImage(); });
        window.addEventListener('resize', function () {
            if (viewer.classList.contains('show')) { fitViewerImage(); }
        });
    });
</script>
