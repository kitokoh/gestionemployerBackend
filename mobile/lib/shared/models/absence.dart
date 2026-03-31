// mobile/lib/shared/models/absence.dart

enum AbsenceStatus { pending, approved, rejected, cancelled }

AbsenceStatus absenceStatusFromString(String s) {
  switch (s) {
    case 'approved': return AbsenceStatus.approved;
    case 'rejected': return AbsenceStatus.rejected;
    case 'cancelled': return AbsenceStatus.cancelled;
    default: return AbsenceStatus.pending;
  }
}

class AbsenceType {
  final int id;
  final String label;
  final String color;
  final bool requiresDocument;
  final bool isPaid;

  const AbsenceType({
    required this.id,
    required this.label,
    required this.color,
    this.requiresDocument = false,
    this.isPaid = true,
  });

  factory AbsenceType.fromJson(Map<String, dynamic> json) => AbsenceType(
    id: json['id'] as int,
    label: json['label'] as String,
    color: json['color'] as String,
    requiresDocument: json['requires_document'] as bool? ?? false,
    isPaid: json['is_paid'] as bool? ?? true,
  );
}

class Absence {
  final int id;
  final AbsenceType type;
  final DateTime startDate;   // TOUJOURS DateTime
  final DateTime endDate;     // TOUJOURS DateTime
  final int daysCount;
  final AbsenceStatus status;
  final String? comment;
  final String? rejectedReason;
  final DateTime? createdAt;

  const Absence({
    required this.id,
    required this.type,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    required this.status,
    this.comment,
    this.rejectedReason,
    this.createdAt,
  });

  factory Absence.fromJson(Map<String, dynamic> json) => Absence(
    id: json['id'] as int,
    type: AbsenceType.fromJson(json['type'] as Map<String, dynamic>),
    startDate: DateTime.parse(json['start_date'] as String),
    endDate: DateTime.parse(json['end_date'] as String),
    daysCount: json['days_count'] as int,
    status: absenceStatusFromString(json['status'] as String),
    comment: json['comment'] as String?,
    rejectedReason: json['rejected_reason'] as String?,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isPending => status == AbsenceStatus.pending;
  bool get isApproved => status == AbsenceStatus.approved;
  bool get canBeCancelled => status == AbsenceStatus.pending;
}
