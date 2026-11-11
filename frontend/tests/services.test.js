import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { formatPrice } from '../js/modules/services.js';

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
