// mobile/lib/shared/models/payroll_slip.dart

enum PayrollStatus { draft, calculated, validated, paid }

PayrollStatus payrollStatusFromString(String s) {
  switch (s) {
    case 'calculated': return PayrollStatus.calculated;
    case 'validated': return PayrollStatus.validated;
    case 'paid': return PayrollStatus.paid;
    default: return PayrollStatus.draft;
  }
}

class PayrollDeductions {
  final double socialSecurity;
  final double incomeTax;
  final double advanceDeduction;
  final double absenceDeduction;

  const PayrollDeductions({
    required this.socialSecurity,
    required this.incomeTax,
    required this.advanceDeduction,
    required this.absenceDeduction,
  });

  factory PayrollDeductions.fromJson(Map<String, dynamic> json) => PayrollDeductions(
    socialSecurity: (json['social_security'] as num).toDouble(),
    incomeTax: (json['income_tax'] as num).toDouble(),
    advanceDeduction: (json['advance_deduction'] as num? ?? 0).toDouble(),
    absenceDeduction: (json['absence_deduction'] as num? ?? 0).toDouble(),
  );

  double get total => socialSecurity + incomeTax + advanceDeduction + absenceDeduction;
}

class PayrollSlip {
  final int id;
  final String period;        // "Avril 2026"
  final int month;
  final int year;
  final double grossSalary;
  final double? overtimePay;
  final PayrollDeductions? deductions;
  final double netSalary;
  final PayrollStatus status;
  final String? pdfUrl;
  final DateTime? validatedAt;

  const PayrollSlip({
    required this.id,
    required this.period,
    required this.month,
    required this.year,
    required this.grossSalary,
    this.overtimePay,
    this.deductions,
    required this.netSalary,
    required this.status,
    this.pdfUrl,
    this.validatedAt,
  });

  factory PayrollSlip.fromJson(Map<String, dynamic> json) => PayrollSlip(
    id: json['id'] as int,
    period: json['period'] as String,
    month: json['month'] as int,
    year: json['year'] as int,
    grossSalary: (json['gross_salary'] as num).toDouble(),
    overtimePay: json['overtime_pay'] != null
        ? (json['overtime_pay'] as num).toDouble()
        : null,
    deductions: json['deductions'] != null
        ? PayrollDeductions.fromJson(json['deductions'] as Map<String, dynamic>)
        : null,
    netSalary: (json['net_salary'] as num).toDouble(),
    status: payrollStatusFromString(json['status'] as String),
    pdfUrl: json['pdf_url'] as String?,
    validatedAt: json['validated_at'] != null
        ? DateTime.parse(json['validated_at'] as String)
        : null,
  );

  bool get isValidated => status == PayrollStatus.validated || status == PayrollStatus.paid;
  bool get hasPdf => pdfUrl != null;
}
