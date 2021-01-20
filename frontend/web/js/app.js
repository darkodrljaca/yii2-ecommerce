$(function() {
    // cart-quantity from $menuItems (main.php):
    const $cartQuantity = $('#cart-quantity');
    
    
    // "add to cart" buttons:
    const $addToCart = $('.btn-add-to-cart');
    
    const $itemQuantities = $('.item-quantity');
    
    // listen on button click:
    $addToCart.click(ev => {
        ev.preventDefault(); // ovo je da posle klika 'add to card' ekran ne ode gore svaki put
        const $this = $(ev.target);
        // directly access:
        const id = $this.closest('.product-item').data('key');
        // pass id:
        console.log(id);
        // ajax request:
        $.ajax({
            method: 'POST',
            // pokupi url sa 'Add to Cart' button-a:
            url: $this.attr('href'),
            data: {id},
            // data: JSON.stringify({id}),
            success: function() {
                console.log(arguments);
                $cartQuantity.text(parseInt($cartQuantity.text() || 0)+1);
            }
        })
    });
    
    $itemQuantities.change(ev => {
        const $this = $(ev.target);
        let $tr = $this.closest('tr');
        const $td = $this.closest('td');
        const id = $tr.data('id');
        $.ajax({
            method: 'post',
            url: $tr.data('url'),
            data: {id, quantity: $this.val()},
            success: function(result) {
                $cartQuantity.text(result.quantity);                
                $td.next().text(result.price);                
            }
        });
    });
});

