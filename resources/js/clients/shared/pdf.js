/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

class PDF {
    constructor(url, canvas) {
        this.url = url;
        this.canvas = canvas;
        this.context = canvas.getContext("2d");
        this.currentPage = 1;
        this.maxPages = 1;
    }

    handlePreviousPage() {
        if (this.currentPage == 1) {
            return;
        }

        this.currentPage -= 1;

        this.handle();
    }

    handleNextPage() {
        if (this.currentPage == 5) {
            return;
        }

        this.currentPage += 1;

        this.handle();
    }

    prepare() {
        let previousPageButton = document.getElementById(
            "previous-page-button"
        );

        let nextPageButton = document.getElementById("next-page-button");

        previousPageButton.addEventListener("click", () =>
            this.handlePreviousPage()
        );

        nextPageButton.addEventListener("click", () => this.handleNextPage());

        return this;
    }

    async handle() {
        let pdf = await pdfjsLib.getDocument(this.url).promise;

        let page = await pdf.getPage(this.currentPage);

        let viewport = await page.getViewport({ scale: 1 });

        this.canvas.height = viewport.height;
        this.canvas.width = viewport.width;

        page.render({
            canvasContext: this.context,
            viewport
        });
    }
}

const url = document.querySelector("meta[name='pdf-url'").content;
const canvas = document.getElementById("pdf-placeholder");

new PDF(url, canvas).prepare().handle();
