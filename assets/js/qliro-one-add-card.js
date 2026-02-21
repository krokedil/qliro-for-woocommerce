window.qccfReady = function (q1) {
    console.log(q1)
    qccf.onCreditCardCreated(function (...ccArgs) {
        console.log('onCreditCardCreated args:', ccArgs)
    })
}