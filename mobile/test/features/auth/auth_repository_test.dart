import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';

void main() {
  group('AuthRepository payload extraction', () {
    test('extracts token from root-level payload', () {
      const token = 'sanctum-token-root';
      final extracted = AuthRepository.extractToken({
        'token': token,
        'data': {'id': 1, 'email': 'a@b.c'},
      });
      expect(extracted, token);
    });

    test('extracts token from nested data payload', () {
      const token = 'sanctum-token-nested';
      final extracted = AuthRepository.extractToken({
        'data': {
          'token': token,
          'user': {'id': 1, 'email': 'a@b.c'},
        },
      });
      expect(extracted, token);
    });

    test('throws FormatException when token is missing', () {
      expect(
        () => AuthRepository.extractToken({'data': {'id': 1}}),
        throwsA(isA<FormatException>()),
      );
    });

    test('extracts employee json from data.user structure', () {
      final payload = {
        'data': {
          'token': 't',
          'user': {'id': 9, 'email': 'leila@acme.test', 'role': 'manager'},
        },
      };
      final employeeJson = AuthRepository.extractEmployeeJson(payload);
      expect(employeeJson['email'], 'leila@acme.test');
      expect(employeeJson['role'], 'manager');
    });

    test('extracts employee json from flat data structure', () {
      final payload = {
        'data': {'id': 9, 'email': 'leila@acme.test', 'role': 'manager'},
      };
      final employeeJson = AuthRepository.extractEmployeeJson(payload);
      expect(employeeJson['email'], 'leila@acme.test');
    });

    test('throws FormatException when employee data is missing', () {
      expect(
        () => AuthRepository.extractEmployeeJson({'token': 't'}),
        throwsA(isA<FormatException>()),
      );
    });
  });
}
