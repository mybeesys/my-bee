document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({fail}) => {
        fail(({status, preventDefault}) => {
            console.log('livewire:init:error', status)
            if (status === 419) {
                confirm('Page expired, please refresh the page to continue.')
                // preventDefault()
                // location.reload();
            } else {

                new FilamentNotification()
                    .title('Something went wrong!')
                    .danger()
                    .persistent()
                    .send()

                preventDefault()
            }
        })
    })
})

// console.log('DOMContentLoaded');
// document.getElementsByName("itemPricingNewPrice")[0].addEventListener('change', function (evt) {
//     console.log('itemPricingNewPrice changed: ', evt)
// });
// document.addEventListener("DOMContentLoaded", function (event) {
//
//
//
// });
