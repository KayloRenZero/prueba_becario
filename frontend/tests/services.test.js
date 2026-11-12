import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { checkoutUrl, formatPrice } from '../js/modules/services.js';

describe('formatPrice', () => {
  it('formatea números en pesos mexicanos con separador de miles', () => {
    assert.equal(formatPrice(15000), '$15,000.00 MXN');
  });

  it('acepta el string que PDO devuelve para columnas DECIMAL', () => {
    assert.equal(formatPrice('3500.00'), '$3,500.00 MXN');
  });

  it('muestra un texto alternativo si el precio no es numérico', () => {
    assert.equal(formatPrice('abc'), 'Precio a consultar');
    assert.equal(formatPrice(null), 'Precio a consultar');
    assert.equal(formatPrice(undefined), 'Precio a consultar');
  });
});

describe('checkoutUrl', () => {
  it('enlaza al checkout con el id del servicio', () => {
    assert.equal(checkoutUrl({ id: 3 }), '../backend/pagos/checkout.php?servicio_id=3');
  });

  it('devuelve null si el id no es un entero positivo', () => {
    assert.equal(checkoutUrl({ id: '3' }), null);
    assert.equal(checkoutUrl({ id: 0 }), null);
    assert.equal(checkoutUrl({ id: -1 }), null);
    assert.equal(checkoutUrl({ id: 1.5 }), null);
    assert.equal(checkoutUrl({}), null);
    assert.equal(checkoutUrl(null), null);
  });

  it('no permite inyectar parámetros extra en la URL', () => {
    assert.equal(checkoutUrl({ id: '1&admin=1' }), null);
  });
});
