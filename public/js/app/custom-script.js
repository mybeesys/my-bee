document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({fail}) => {
        fail(({status, preventDefault}) => {
            console.log('livewire:init:error', status)
            if (status === 419) {
                confirm('Page expired, please refresh the page to continue.')
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
