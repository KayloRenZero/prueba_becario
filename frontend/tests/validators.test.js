import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { contactRules, validateField, validateForm } from '../js/modules/validators.js';

const check = (field, value) => validateField(value, contactRules[field]);

describe('nombre', () => {
  it('es obligatorio', () => {
    assert.match(check('nombre', ''), /obligatorio/);
  });

  it('trata un valor de solo espacios como vacío', () => {
    assert.match(check('nombre', '    '), /obligatorio/);
  });

  it('exige al menos 3 caracteres', () => {
    assert.match(check('nombre', 'Al'), /al menos 3/);
    assert.match(check('nombre', '  Al  '), /al menos 3/);
  });

  it('acepta 3 caracteres o más', () => {
    assert.equal(check('nombre', 'Ana'), null);
    assert.equal(check('nombre', 'José Pérez'), null);
  });
});

describe('email', () => {
  it('es obligatorio', () => {
    assert.match(check('email', ''), /obligatorio/);
  });

  for (const invalid of ['ana', 'ana@', 'ana@empresa', 'ana @empresa.com', 'ana@empresa.c', '@empresa.com']) {
    it(`rechaza "${invalid}"`, () => {
      assert.match(check('email', invalid), /correo válido/);
    });
  }

  for (const valid of ['ana@empresa.com', 'ana.perez+ventas@sub.empresa.mx']) {
    it(`acepta "${valid}"`, () => {
      assert.equal(check('email', valid), null);
    });
  }
});

describe('telefono', () => {
  it('es obligatorio', () => {
    assert.match(check('telefono', ''), /obligatorio/);
  });

  for (const invalid of ['551234567', '55123456789', '55 1234 5678', '55-1234-5678', 'abcdefghij', '+525512345678']) {
    it(`rechaza "${invalid}"`, () => {
      assert.match(check('telefono', invalid), /exactamente 10 dígitos/);
    });
  }

  it('acepta exactamente 10 dígitos', () => {
    assert.equal(check('telefono', '5512345678'), null);
  });
});

describe('mensaje', () => {
  it('es obligatorio', () => {
    assert.match(check('mensaje', ''), /obligatorio/);
  });

  it('exige al menos 10 caracteres sin contar espacios de los extremos', () => {
    assert.match(check('mensaje', 'Hola'), /al menos 10/);
    assert.match(check('mensaje', '   corto   '), /al menos 10/);
  });

  it('acepta exactamente 10 caracteres (límite)', () => {
    assert.equal(check('mensaje', '1234567890'), null);
  });
});

describe('validateForm', () => {
  it('devuelve un objeto vacío si todo es válido', () => {
    const values = {
      nombre: 'Ana López',
      email: 'ana@empresa.com',
      telefono: '5512345678',
      mensaje: 'Quiero una cotización.',
    };
    assert.deepEqual(validateForm(values, contactRules), {});
  });

  it('reporta todos los campos inválidos en el orden del formulario', () => {
    const errors = validateForm({}, contactRules);
    assert.deepEqual(Object.keys(errors), ['nombre', 'email', 'telefono', 'mensaje']);
  });

  it('reporta solo los campos con error', () => {
    const errors = validateForm({ nombre: 'Ana', email: 'mal', telefono: '5512345678', mensaje: 'x' }, contactRules);
    assert.deepEqual(Object.keys(errors), ['email', 'mensaje']);
  });
});
