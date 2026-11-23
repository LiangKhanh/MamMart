(function(){
  function hint(){
    document.querySelectorAll('.wc-block-cart-items__row').forEach(function(row){
      var i=row.querySelector('input.wc-block-components-quantity-selector__input');
      if(!i||!i.max||row.querySelector('.mm-stock-cap')) return;
      var s=document.createElement('div'); s.className='mm-stock-cap mm-stock-low'; s.style.fontSize='.85rem';
      s.textContent='Tối đa '+i.max+' sản phẩm (theo tồn kho)';
      var host=row.querySelector('.wc-block-cart-item__product')||row; host.appendChild(s);
    });
  }
  new MutationObserver(hint).observe(document.body,{childList:true,subtree:true}); hint();
})();
