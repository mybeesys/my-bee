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

function isLivewireFileUploadRequest(uri) {
    return typeof uri === 'string' && uri.includes('livewire/upload-file')
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ uri, fail }) => {
        fail(({ status, preventDefault }) => {
            if (isLivewireFileUploadRequest(uri)) {
                return
            }

            if (status === 419 || status === 401) {
                preventDefault?.()
                redirectToLogin()

                return
            }

            if (status >= 500) {
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
