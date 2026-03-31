// mobile/lib/shared/models/attendance_log.dart

enum AttendanceStatus { ontime, late, earlyLeave, absent, incomplete, leave, holiday }
enum AttendanceMethod { mobile, qr, biometric, manual }

class AttendanceLog {
  final int id;
  final String date;           // Format: "2026-04-15"
  final int sessionNumber;     // 1 = session principale, 2 = split-shift
  final DateTime? checkIn;     // TOUJOURS DateTime — jamais String
  final DateTime? checkOut;    // TOUJOURS DateTime — jamais String
  final double? hoursWorked;
  final double? overtimeHours;
  final String status;
  final String method;
  final bool isManualEdit;

  const AttendanceLog({
    required this.id,
    required this.date,
    this.sessionNumber = 1,
    this.checkIn,
    this.checkOut,
    this.hoursWorked,
    this.overtimeHours,
    required this.status,
    required this.method,
    this.isManualEdit = false,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) => AttendanceLog(
    id: json['id'] as int,
    date: json['date'] as String,
    sessionNumber: json['session_number'] as int? ?? 1,
    checkIn: json['check_in'] != null
        ? DateTime.parse(json['check_in'] as String).toLocal()
        : null,
    checkOut: json['check_out'] != null
        ? DateTime.parse(json['check_out'] as String).toLocal()
        : null,
    hoursWorked: json['hours_worked'] != null
        ? (json['hours_worked'] as num).toDouble()
        : null,
    overtimeHours: json['overtime_hours'] != null
        ? (json['overtime_hours'] as num).toDouble()
        : null,
    status: json['status'] as String,
    method: json['method'] as String,
    isManualEdit: json['is_manual_edit'] as bool? ?? false,
  );

  bool get hasCheckedIn => checkIn != null;
  bool get hasCheckedOut => checkOut != null;
  bool get isComplete => checkIn != null && checkOut != null;
  bool get isLate => status == 'late';
  bool get isAbsent => status == 'absent';

  AttendanceLog copyWith({
    DateTime? checkIn,
    DateTime? checkOut,
    double? hoursWorked,
    String? status,
  }) => AttendanceLog(
    id: id,
    date: date,
    checkIn: checkIn ?? this.checkIn,
    checkOut: checkOut ?? this.checkOut,
    hoursWorked: hoursWorked ?? this.hoursWorked,
    overtimeHours: overtimeHours,
    status: status ?? this.status,
    method: method,
    isManualEdit: isManualEdit,
  );
}

class AttendanceTodayContext {
  final bool isHoliday;
  final bool isLeave;
  final String? expectedStart;

  const AttendanceTodayContext({
    this.isHoliday = false,
    this.isLeave = false,
    this.expectedStart,
  });

  factory AttendanceTodayContext.fromJson(Map<String, dynamic> json) =>
      AttendanceTodayContext(
        isHoliday: json['is_holiday'] as bool? ?? false,
        isLeave: json['is_leave'] as bool? ?? false,
        expectedStart: json['expected_start'] as String?,
      );
}
