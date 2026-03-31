import nodemailer from 'nodemailer'

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST,
  port: Number(process.env.SMTP_PORT),
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS,
  },
})

export async function sendEmail({
  to,
  subject,
  html,
}: {
  to: string
  subject: string
  html: string
}) {
  await transporter.sendMail({
    from: process.env.SMTP_FROM,
    to,
    subject,
    html,
  })
}

export function passwordResetEmailHtml(resetUrl: string): string {
  return `
    <div style="font-family:Inter,system-ui,sans-serif;max-width:480px;margin:0 auto;background:#0f0f0f;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="font-size:18px;font-weight:600;margin-bottom:8px;">Reset your password</h2>
      <p style="font-size:14px;color:#888;margin-bottom:24px;">Click the link below to reset your password. This link expires in 1 hour.</p>
      <a href="${resetUrl}" style="display:inline-block;background:#fff;color:#000;font-size:13px;font-weight:600;padding:10px 20px;border-radius:6px;text-decoration:none;">Reset Password</a>
      <p style="font-size:12px;color:#444;margin-top:24px;">If you didn't request this, ignore this email.</p>
    </div>
  `
}

export function verifyEmailHtml(verifyUrl: string): string {
  return `
    <div style="font-family:Inter,system-ui,sans-serif;max-width:480px;margin:0 auto;background:#0f0f0f;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="font-size:18px;font-weight:600;margin-bottom:8px;">Verify your email</h2>
      <p style="font-size:14px;color:#888;margin-bottom:24px;">Click below to verify your email and activate your account.</p>
      <a href="${verifyUrl}" style="display:inline-block;background:#fff;color:#000;font-size:13px;font-weight:600;padding:10px 20px;border-radius:6px;text-decoration:none;">Verify Email</a>
    </div>
  `
}
