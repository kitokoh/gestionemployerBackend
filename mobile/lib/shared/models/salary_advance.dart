// mobile/lib/shared/models/salary_advance.dart

enum AdvanceStatus { pending, approved, rejected, active, repaid }
// 'active' = avance approuvée en cours de remboursement (PayrollService)

AdvanceStatus advanceStatusFromString(String s) {
  switch (s) {
    case 'approved': return AdvanceStatus.approved;
    case 'rejected': return AdvanceStatus.rejected;
    case 'active':   return AdvanceStatus.active;
    case 'repaid':   return AdvanceStatus.repaid;
    default: return AdvanceStatus.pending;
  }
}

class SalaryAdvance {
  final int id;
  final double amount;
  final double amountRemaining;
  final String reason;
  final AdvanceStatus status;
  final int? repaymentMonths;
  final double? monthlyDeduction;
  final DateTime? approvedAt;
  final DateTime? createdAt;

  const SalaryAdvance({
    required this.id,
    required this.amount,
    required this.amountRemaining,
    required this.reason,
    required this.status,
    this.repaymentMonths,
    this.monthlyDeduction,
    this.approvedAt,
    this.createdAt,
  });

  factory SalaryAdvance.fromJson(Map<String, dynamic> json) => SalaryAdvance(
    id: json['id'] as int,
    amount: (json['amount'] as num).toDouble(),
    amountRemaining: (json['amount_remaining'] as num).toDouble(),
    reason: json['reason'] as String,
    status: advanceStatusFromString(json['status'] as String),
    repaymentMonths: json['repayment_months'] as int?,
    monthlyDeduction: json['monthly_deduction'] != null
        ? (json['monthly_deduction'] as num).toDouble()
        : null,
    approvedAt: json['approved_at'] != null
        ? DateTime.parse(json['approved_at'] as String)
        : null,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isPending => status == AdvanceStatus.pending;
  bool get isApproved => status == AdvanceStatus.approved;
  bool get isActive => status == AdvanceStatus.active;  // En cours de remboursement
  bool get isFullyRepaid => amountRemaining <= 0;
  double get repaidAmount => amount - amountRemaining;
  double get repaidPercentage => amount > 0 ? (repaidAmount / amount) * 100 : 0;
}
