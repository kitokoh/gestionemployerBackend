import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/models/monthly_summary.dart';

void main() {
  group('MonthlySummary.fromJson', () {
    test('parses full payload with breakdown and totals as numbers', () {
      // Contrat /api/v1/me/monthly-summary : totals en num, breakdown liste.
      final summary = MonthlySummary.fromJson({
        'employee_id': 12,
        'name': 'Bob',
        'year': 2026,
        'month': 4,
        'currency': 'DZD',
        'period': {
          'from': '2026-04-01',
          'to': '2026-04-30',
          'working_days': 22,
          'days_present': 20,
          'days_absent': 2,
        },
        'totals': {
          'hours': 128,
          'overtime_hours': 24,
          'gross': 79000,
          'deductions': 8690,
          'net': 70310,
        },
        'breakdown': [
          {
            'date': '2026-04-01',
            'hours': 8,
            'overtime_hours': 2,
            'base_gain': 3600,
            'overtime_gain': 1125,
            'total': 4725,
          },
        ],
        'disclaimer': 'Estimation indicative.',
      });

      expect(summary.employeeId, 12);
      expect(summary.name, 'Bob');
      expect(summary.currency, 'DZD');
      expect(summary.workingDays, 22);
      expect(summary.daysPresent, 20);
      expect(summary.daysAbsent, 2);
      expect(summary.hours, 128.0);
      expect(summary.overtimeHours, 24.0);
      expect(summary.gross, 79000.0);
      expect(summary.deductions, 8690.0);
      expect(summary.net, 70310.0);
      expect(summary.breakdown, hasLength(1));
      expect(summary.breakdown.first.total, 4725.0);
      expect(summary.periodFrom.year, 2026);
      expect(summary.periodFrom.month, 4);
      expect(summary.periodTo.day, 30);
    });

    test('coerces totals from strings (API may serialise decimals as strings)', () {
      final summary = MonthlySummary.fromJson({
        'employee_id': 1,
        'name': 'Alice',
        'currency': 'DZD',
        'period': {
          'from': '2026-04-01',
          'to': '2026-04-30',
          'working_days': 22,
          'days_present': 22,
          'days_absent': 0,
        },
        'totals': {
          'hours': '176.50',
          'overtime_hours': '0.00',
          'gross': '88250.00',
          'deductions': '9707.50',
          'net': '78542.50',
        },
        'breakdown': [],
        'disclaimer': '',
      });

      expect(summary.hours, 176.5);
      expect(summary.gross, 88250.0);
      expect(summary.net, 78542.5);
    });

    test('defaults totals to 0 and disclaimer to empty', () {
      final summary = MonthlySummary.fromJson({
        'employee_id': 5,
        'period': {
          'from': '2026-04-01',
          'to': '2026-04-30',
          'working_days': 22,
          'days_present': 0,
          'days_absent': 22,
        },
        'totals': {},
        'breakdown': null,
      });

      expect(summary.hours, 0.0);
      expect(summary.overtimeHours, 0.0);
      expect(summary.gross, 0.0);
      expect(summary.net, 0.0);
      expect(summary.disclaimer, '');
      expect(summary.breakdown, isEmpty);
    });
  });
}
