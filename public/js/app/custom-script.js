document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({fail}) => {
        fail(({status, preventDefault}) => {
            if (status === 419) {
                confirm('Page expired, please refresh the page to continue.')
            } else if (status >= 500) {
                // Server error — logged in Laravel; avoid noisy console + duplicate toasts.
                preventDefault()
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
