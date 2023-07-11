console.log("hello");
function updateStockDecrementado(order_id, stock_decrementado) {
  fetch(`/admin/orders/${order_id}/decremento`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      order_id: order_id,
      stock_decrementado: stock_decrementado
    })
  }).then(function(response) {
    // código para manejar la respuesta del servidor
  });
}
