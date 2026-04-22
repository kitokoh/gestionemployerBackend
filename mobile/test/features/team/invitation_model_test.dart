import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/features/team/data/employee_repository.dart';

void main() {
  group('Invitation.fromJson', () {
    test('parses UUID id as String (regression against PR #76)', () {
      // Les invitations utilisent une PK UUID cote API. Avant le fix
      // PR #76, le modele mobile castait id en int et plantait avec
      // "TypeError: String is not a subtype of num".
      const uuid = 'a19a9565-a44e-41cb-969f-7cd7c8844bb8';
      final invitation = Invitation.fromJson({
        'id': uuid,
        'email': 'rose@acme.test',
        'status': 'pending',
      });

      expect(invitation.id, uuid);
      expect(invitation.id, isA<String>());
    });

    test('still accepts legacy int id (defensive)', () {
      // Si une ancienne API renvoie encore un int, on convertit en String
      // sans crasher.
      final invitation = Invitation.fromJson({
        'id': 42,
        'email': 'legacy@acme.test',
        'status': 'pending',
      });

      expect(invitation.id, '42');
    });

    test('reads sentAt from last_sent_at (API field name)', () {
      // L'API expose le dernier renvoi sous last_sent_at, pas sent_at.
      final invitation = Invitation.fromJson({
        'id': 'uuid-1',
        'email': 'a@b.c',
        'status': 'sent',
        'last_sent_at': '2026-04-22T10:15:00Z',
        'expires_at': '2026-04-29T10:15:00Z',
      });

      expect(invitation.sentAt, isNotNull);
      expect(invitation.sentAt!.toUtc().hour, 10);
      expect(invitation.expiresAt, isNotNull);
    });

    test('falls back to sent_at when last_sent_at is missing', () {
      final invitation = Invitation.fromJson({
        'id': 'uuid-2',
        'email': 'a@b.c',
        'status': 'sent',
        'sent_at': '2026-04-22T10:15:00Z',
      });

      expect(invitation.sentAt, isNotNull);
    });

    test('defaults status to pending when absent', () {
      final invitation = Invitation.fromJson({
        'id': 'uuid-3',
        'email': 'a@b.c',
      });

      expect(invitation.status, 'pending');
    });

    test('parses employeeId as int when present', () {
      final invitation = Invitation.fromJson({
        'id': 'uuid-4',
        'email': 'a@b.c',
        'status': 'pending',
        'employee_id': 7,
      });

      expect(invitation.employeeId, 7);
    });

    test('employeeId is null when missing or non-numeric', () {
      final i1 = Invitation.fromJson({'id': 'x', 'email': 'a@b.c'});
      final i2 = Invitation.fromJson({'id': 'x', 'email': 'a@b.c', 'employee_id': 'abc'});

      expect(i1.employeeId, isNull);
      expect(i2.employeeId, isNull);
    });
  });
}
