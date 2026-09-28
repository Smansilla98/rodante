import { normalizeScan } from '../src/scan/normalize';

describe('normalizeScan', () => {
  it('keeps a bare token or plate', () => {
    expect(normalizeScan('  AB123CD  ')).toBe('AB123CD');
  });

  it('extracts the token from a Rodante QR url', () => {
    expect(normalizeScan('https://rodante.example/qr/tok%2Fen')).toBe('tok/en');
  });
});
