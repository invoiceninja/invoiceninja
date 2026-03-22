/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class PDF {
    constructor(url, canvas) {
        this.url = url;
        this.canvas = canvas;
        this.context = canvas.getContext('2d');
        this.currentPage = 1;
        this.maxPages = 1;
        this.currentScale = 1;
        this.currentScaleText = document.getElementById('zoom-level');

        if (matchMedia('only screen and (max-width: 480px)').matches) {
            this.currentScale = 1;
        }

        this.currentScaleText.textContent = this.currentScale * 100 + '%';
    }

    handlePreviousPage() {
        if (this.currentPage == 1) {
            return;
        }

        this.currentPage -= 1;

        this.handle();
    }

    handleNextPage() {
        if (this.currentPage == this.maxPages) {
            return;
        }

        this.currentPage += 1;

        this.handle();
    }

    handleZoomChange(zoom = null) {
        if (this.currentScale == 0.25 && !zoom) {
            return;
        }

        if (zoom) {
            this.currentScale += 0.25;
            this.currentScaleText.textContent = this.currentScale * 100 + '%';

            return this.handle();
        }

        this.currentScale -= 0.25;
        this.currentScaleText.textContent = this.currentScale * 100 + '%';

        return this.handle();
    }

    prepare() {
        let previousPageButton = document
            .getElementById('previous-page-button')
            .addEventListener('click', () => this.handlePreviousPage());

        let nextPageButton = document
            .getElementById('next-page-button')
            .addEventListener('click', () => this.handleNextPage());

        let zoomInButton = document
            .getElementById('zoom-in')
            .addEventListener('click', () => this.handleZoomChange(true));

        let zoomOutButton = document
            .getElementById('zoom-out')
            .addEventListener('click', () => this.handleZoomChange());

        document
            .querySelector('meta[name=pdf-url]')
            .addEventListener('change', () => {
                this.canvas.getContext('2d').clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.url = document.querySelector("meta[name='pdf-url']").content;

                this.handle();
            })

        return this;
    }

    setPagesInViewport() {
        let currentPageContainer = document.getElementById(
            'current-page-container'
        );

        let totalPageContainer = document.getElementById(
            'total-page-container'
        );

        let paginationButtonContainer = document.getElementById(
            'pagination-button-container'
        );

        currentPageContainer.innerText = this.currentPage;
        totalPageContainer.innerText = this.maxPages;

        if (this.maxPages > 1) {
            paginationButtonContainer.style.display = 'flex';
        }
    }

    async handle() {
        let pdf = await pdfjsLib.getDocument(this.url).promise;

        let page = await pdf.getPage(this.currentPage);

        this.maxPages = pdf.numPages;

        let viewport = await page.getViewport({ scale: this.currentScale });

        this.canvas.height = viewport.height;
        this.canvas.width = viewport.width;

        page.render({
            canvasContext: this.context,
            viewport,
        });

        this.setPagesInViewport();

        /* document.addEventListener("webviewerloaded", function () {
            PDFViewerApplication.initializedPromise.then(function () {
                PDFViewerApplication.eventBus.on("pagerendered", function handler(event) {
                    try {
                        if (window.chrome && !/Edge/.test(navigator.userAgent) && !event.source.__rerendered) {
                            console.log("Chrome-like browser detected, re-rendering page %d for the first time.", event.pageNumber);
                            var currentScale = event.source.scale;
                            event.source.__rerendered = true;
                            event.source.update(currentScale * 0.99);
                            event.source.update(currentScale);
                        }
                    } catch (e) {
                        console.error(e);
                    }
                });
            });
        }); */
    }
}

const url = document.querySelector("meta[name='pdf-url']").content;
const canvas = document.getElementById('pdf-placeholder');

new PDF(url, canvas).prepare().handle();
