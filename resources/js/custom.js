function resolveLoginUrl() {
    const metaUrl = document.querySelector('meta[name="app-login-url"]')?.content

    if (metaUrl) {
        return metaUrl
    }

    return `${window.location.origin}/login`
}

function redirectToLogin() {
    window.location.replace(resolveLoginUrl())
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419 || status === 401) {
                preventDefault?.()
                redirectToLogin()

                return
            }

            if (status >= 500) {
                preventDefault?.()

                return
            }

            if (typeof FilamentNotification !== 'undefined') {
                new FilamentNotification()
                    .title('Something went wrong!')
                    .danger()
                    .persistent()
                    .send()
            }

            preventDefault?.()
        })
    })
})
