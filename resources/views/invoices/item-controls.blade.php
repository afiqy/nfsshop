<script>
    const items = document.getElementById('invoice-items');
    const addItem = document.getElementById('add-item');
    let nextIndex = items.children.length;
    function refreshButtons() {
        addItem.disabled = items.children.length >= Number(items.dataset.maxItems);
        for (const button of items.querySelectorAll('[data-remove-item]')) {
            button.hidden = items.children.length < 2;
        }
    }
    addItem.hidden = false;
    addItem.addEventListener('click', () => {
        const template = document.getElementById('invoice-item-template');
        items.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        items.lastElementChild.querySelector('input').focus();
        refreshButtons();
    });
    items.addEventListener('click', event => {
        if (!event.target.matches('[data-remove-item]') || items.children.length < 2) return;
        event.target.closest('fieldset').remove();
        addItem.focus();
        refreshButtons();
    });
    refreshButtons();
</script>
