// mobile/lib/shared/models/app_notification.dart

enum NotificationType {
  absenceApproved,
  absenceRejected,
  advanceApproved,
  advanceRejected,
  taskAssigned,
  taskCommented,
  payslipAvailable,
  evaluationReceived,
  unknown,
}

NotificationType notificationTypeFromString(String s) {
  switch (s) {
    case 'absence_approved': return NotificationType.absenceApproved;
    case 'absence_rejected': return NotificationType.absenceRejected;
    case 'advance_approved': return NotificationType.advanceApproved;
    case 'advance_rejected': return NotificationType.advanceRejected;
    case 'task_assigned': return NotificationType.taskAssigned;
    case 'task_commented': return NotificationType.taskCommented;
    case 'payslip_available': return NotificationType.payslipAvailable;
    case 'evaluation_received': return NotificationType.evaluationReceived;
    default: return NotificationType.unknown;
  }
}

class AppNotification {
  final int id;
  final NotificationType type;
  final String title;
  final String body;
  final Map<String, dynamic>? data;   // données additionnelles (ex: absence_id)
  final bool isRead;
  final DateTime createdAt;           // TOUJOURS DateTime

  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.data,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
    id: json['id'] as int,
    type: notificationTypeFromString(json['type'] as String),
    title: json['title'] as String,
    body: json['body'] as String,
    data: json['data'] as Map<String, dynamic>?,
    isRead: json['is_read'] as bool,
    createdAt: DateTime.parse(json['created_at'] as String),
  );

  AppNotification copyWith({bool? isRead}) => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    data: data,
    isRead: isRead ?? this.isRead,
    createdAt: createdAt,
  );
}
