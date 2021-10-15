<style>
    *,
    *::after,
    *::before {
        box-sizing: border-box
    }

    #payment-form {
        width: 100%;
    }

    iframe {
        width: 100%
    }

    .one-liner {
        display: flex;
        flex-direction: column
    }

    #pay-button {
        border: none;
        border-radius: 3px;
        color: #FFF;
        font-weight: 500;
        height: 40px;
        width: 100%;
        background-color: #13395E;
        box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.4)
    }

    #pay-button:active {
        background-color: #0B2A49;
        box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.4)
    }

    #pay-button:hover {
        background-color: #15406B;
        box-shadow: 0 2px 5px 0 rgba(19, 57, 94, 0.4)
    }

    #pay-button:disabled {
        background-color: #697887;
        box-shadow: none
    }

    #pay-button:not(:disabled) {
        cursor: pointer
    }

    .card-frame {
        border: solid 1px #13395E;
        border-radius: 3px;
        width: 100%;
        margin-bottom: 8px;
        height: 40px;
        box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.2)
    }

    .card-frame.frame--rendered {
        opacity: 1
    }

    .card-frame.frame--rendered.frame--focus {
        border: solid 1px #13395E;
        box-shadow: 0 2px 5px 0 rgba(19, 57, 94, 0.15)
    }

    .card-frame.frame--rendered.frame--invalid {
        border: solid 1px #D96830;
        box-shadow: 0 2px 5px 0 rgba(217, 104, 48, 0.15)
    }

    .success-payment-message {
        color: #13395E;
        line-height: 1.4
    }

    .token {
        color: #b35e14;
        font-size: 0.9rem;
        font-family: monospace
    }

    @media screen and (min-width: 31rem) {
        .one-liner {
            flex-direction: row
        }

        .card-frame {
            width: 318px;
            margin-bottom: 0
        }

        #pay-button {
            width: 175px;
            margin-left: 8px
        }
    }

</style>
