class DailySummary {
  final double baseGain;
  final double overtimeHours;
  final double overtimeGain;
  final double totalEstimated;
  final String currency;

  DailySummary({
    required this.baseGain,
    required this.overtimeHours,
    required this.overtimeGain,
    required this.totalEstimated,
    required this.currency,
  });

  factory DailySummary.fromJson(Map<String, dynamic> json) {
    return DailySummary(
      baseGain: double.tryParse((json['base_gain'] ?? 0).toString()) ?? 0.0,
      overtimeHours: double.tryParse((json['overtime_hours'] ?? 0).toString()) ?? 0.0,
      overtimeGain: double.tryParse((json['overtime_gain'] ?? 0).toString()) ?? 0.0,
      totalEstimated: double.tryParse((json['total_estimated'] ?? 0).toString()) ?? 0.0,
      currency: json['currency'] ?? 'DA',
    );
  }
}
