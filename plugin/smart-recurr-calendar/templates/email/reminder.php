<?php
/**
 * HTML reminder email template.
 *
 * Variables expected: $customer_name, $service_name, $tech_name, $date,
 * $location_type, $company_name, $link.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $customer_name, $service_name, $tech_name, $date, $location_type, $company_name, $link */
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; padding: 24px;">
	<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
		<div style="background: #4f46e5; color: #fff; padding: 20px 24px;">
			<h1 style="margin: 0; font-size: 18px;"><?php echo esc_html( $company_name ?? 'SmartRecur' ); ?></h1>
		</div>
		<div style="padding: 24px;">
			<p>Hi <?php echo esc_html( $customer_name ?? '' ); ?>,</p>
			<p>This is a reminder that we have scheduled a <strong><?php echo esc_html( $service_name ?? '' ); ?></strong> appointment for you on <strong><?php echo esc_html( $date ?? '' ); ?></strong>.</p>
			<p>Technician: <?php echo esc_html( $tech_name ?? 'TBD' ); ?><br>
			   Location: <?php echo esc_html( $location_type ?? '' ); ?></p>
			<?php if ( ! empty( $link ) ) : ?>
				<p style="margin: 24px 0;">
					<a href="<?php echo esc_url( $link ); ?>" style="display: inline-block; background: #4f46e5; color: #fff; padding: 10px 16px; border-radius: 8px; text-decoration: none;">Confirm or reschedule</a>
				</p>
			<?php endif; ?>
			<p style="margin-top: 32px; color: #6b7280; font-size: 13px;">Met vriendelijke groet,<br><?php echo esc_html( $company_name ?? '' ); ?></p>
		</div>
	</div>
</body>
</html>
