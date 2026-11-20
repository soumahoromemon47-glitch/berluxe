content = open(r'c:\xampp\htdocs\berluxe\assets\js\app.js', 'r', encoding='utf-8').read()

start = "document.addEventListener('submit', function(event) {"
end = "});" + chr(10) + "document.addEventListener('submit', (event) => {"

start_idx = content.find(start)
end_idx = content.find(end)

if start_idx >= 0 and end_idx >= 0:
    end_idx = end_idx + len(end)
    old_block = content[start_idx:end_idx]
    
    new_block = """document.addEventListener('submit', function(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('cart-add-form')) {
        return;
    }

    // On bloque immÃ©diatement la redirection, quoi qu'il arrive
    event.preventDefault(); 
    event.stopPropagation();

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.innerHTML : '';
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '...'; # Petit feedback visuel
    }

    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur rÃ©seau');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            # Mettre Ã  jour le compteur du panier
            const countBadge = document.querySelector('[data-cart-count-badge]');
            if (countBadge) {
                countBadge.textContent = data.count;
                countBadge.hidden = false;
            }
            
            # Afficher le petit message de succÃ¨s sous le bouton
            const feedback = form.parentElement.querySelector('[data-cart-add-feedback]');
            if (feedback) {
                feedback.textContent = data.message;
                feedback.hidden = false;
                feedback.style.color = '#2F7D4F';
            }
        } else:
            alert(data.message || "Erreur lors de l'ajout.")
    })
    .catch(error => {
        console.error('Erreur:', error)
        alert("Impossible d'ajouter au panier. VÃ©rifie ta connexion.")
    })
    .finally(() => {
        if (submitButton) {
            submitButton.disabled = false
            submitButton.innerHTML = originalText
        }
    })
}\""""

    content = content.replace(old_block, new_block)
    open(r'c:\xampp\htdocs\berluxe\assets\js\app.js', 'w', encoding='utf-8').write(content)
    print('SUCCES: Bloc remplace')
else:
    print(f'ECHEC: start_idx={start_idx}, end_idx={end_idx}')
